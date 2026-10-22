<?php
/**
 * Plugin Name: Kashida Video Embed
 * Plugin URI: https://kashida.ly/plugins/kashida-video-embed/
 * Description: Embed videos by pasting a supported video URL, without copying embed code.
 * Version: 1.4.22
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Kashida Media Services
 * Author URI: https://kashida.ly/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kashida-video-embed
 * Domain Path: /languages
 */
if (!defined('ABSPATH')) exit;

/**
 * Add official plugin page links to the plugin row in WordPress admin.
 */
function kve_plugin_row_meta_links($links, $file) {
    if (plugin_basename(__FILE__) !== $file) {
        return $links;
    }

    $links[] = '<a href="https://wordpress.org/plugins/kashida-video-embed/" target="_blank" rel="noopener noreferrer">' . esc_html__('View plugin on WordPress.org', 'kashida-video-embed') . '</a>';
    $links[] = '<a href="https://kashida.ly/wp-plugins/kashida-video-embed/" target="_blank" rel="noopener noreferrer">' . esc_html__('View plugin on Kashida website', 'kashida-video-embed') . '</a>';

    return $links;
}
add_filter('plugin_row_meta', 'kve_plugin_row_meta_links', 10, 2);


final class Kashida_Video_Embed {
    const VERSION = '1.4.22';
    const CPT = 'kve_video';
    public function __construct() {
        add_action('init', array($this, 'register_content_type'));
        add_action('init', array($this, 'register_block'));
        add_action('enqueue_block_editor_assets', array($this, 'block_editor_assets'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_assets'));
        add_action('admin_post_kve_save_video', array($this, 'save_video'));
        add_action('admin_post_kve_delete_video', array($this, 'delete_video'));
        add_action('admin_post_kve_save_collection', array($this, 'save_collection'));
        add_action('admin_post_kve_delete_collection', array($this, 'delete_collection'));
        add_action('wp_ajax_kve_preview_video', array($this, 'ajax_preview_video'));
        add_action('wp_enqueue_scripts', array($this, 'provider_scripts'));
        add_shortcode('kashida_video_embed', array($this, 'shortcode'));
        add_shortcode('kashida_video_gallery', array($this, 'gallery_shortcode'));
        add_shortcode('kashida_video_collection', array($this, 'saved_collection_shortcode'));
    }
    public function register_content_type() {
        register_post_type(self::CPT, array('labels'=>array('name'=>__('Videos','kashida-video-embed'),'singular_name'=>__('Video','kashida-video-embed')),'public'=>false,'show_ui'=>false,'show_in_rest'=>false,'supports'=>array('title')));
    }
    public function block_editor_assets() {
        wp_register_script('kve-block-editor', plugin_dir_url(__FILE__) . 'block/editor.js', array('wp-blocks','wp-element','wp-block-editor','wp-components'), self::VERSION, true);
    }
    public function register_block() {
        if (function_exists('register_block_type')) {
            wp_register_script('kve-block-editor', plugin_dir_url(__FILE__) . 'block/editor.js', array('wp-blocks','wp-element','wp-block-editor','wp-components'), self::VERSION, true);
            register_block_type(__DIR__ . '/block', array('render_callback'=>array($this,'render_block')));
        }
    }
    public function render_block($attributes) {
        $url = isset($attributes['url']) ? esc_url_raw($attributes['url']) : '';
        $ratio = isset($attributes['ratio']) ? sanitize_text_field($attributes['ratio']) : '16:9';
        return $this->render_video($url, $ratio, !empty($attributes['controls']), !empty($attributes['lazy']));
    }
    public function admin_menu() {
        add_menu_page(__('Kashida Video Embed','kashida-video-embed'), __('Kashida Video Embed','kashida-video-embed'), 'edit_posts', 'kve-videos', array($this,'videos_page'), 'dashicons-video-alt3', 58);
        add_submenu_page('kve-videos', __('All Videos','kashida-video-embed'), __('All Videos','kashida-video-embed'), 'edit_posts', 'kve-videos', array($this,'videos_page'));
        add_submenu_page('kve-videos', __('Add New','kashida-video-embed'), __('Add New','kashida-video-embed'), 'edit_posts', 'kve-add-new', array($this,'add_page'));
        add_submenu_page('kve-videos', __('Video Collections','kashida-video-embed'), __('Video Collections','kashida-video-embed'), 'edit_posts', 'kve-collections', array($this,'collections_page'));
        add_submenu_page('kve-videos', __('Help','kashida-video-embed'), __('Help','kashida-video-embed'), 'edit_posts', 'kve-help', array($this,'help_page'));
    }
    public function frontend_assets() {
        wp_enqueue_style('kve-frontend', plugin_dir_url(__FILE__) . 'assets/frontend.css', array(), self::VERSION);
        global $post;
        if ($post instanceof WP_Post && (has_shortcode($post->post_content, 'kashida_video_gallery') || has_shortcode($post->post_content, 'kashida_video_collection'))) wp_enqueue_script('jquery');
        $gallery_js = <<<'KVEJS'
jQuery(function($){$('.kve-gallery').each(function(){var g=$(this),track=g.find('.kve-gallery-track'),playlist=g.find('.kve-featured-playlist'),ivl=Math.max(2000,(parseInt($(this).attr('data-interval'),10)||5)*1000);function syncPlaylistHeight(){if(!playlist.length)return;var main=g.find('.kve-featured-main');if(window.matchMedia('(max-width:700px)').matches){playlist.css({height:'',maxHeight:'',overflowY:'',alignSelf:''});main.css({height:'',minHeight:''});g.find('.kve-featured-player,.kve-featured-main .kve-gallery-player').css({height:'',minHeight:''});return;}var ph=parseInt(g.attr('data-playlist-height'),10),h=ph>0?ph:420;if(h>0){main.css({height:h+'px',minHeight:h+'px'});g.find('.kve-featured-player').css({height:'100%',minHeight:0});g.find('.kve-featured-main .kve-gallery-player').css({height:'calc(100% - '+(g.find('.kve-featured-main .kve-gallery-title').outerHeight()||0)+'px)',minHeight:0});playlist.css({height:h+'px',maxHeight:h+'px',overflowY:(g.attr('data-scroll')==='0'?'hidden':'auto'),alignSelf:'start'});}}if(playlist.length){syncPlaylistHeight();$(window).on('resize.kveFeatured orientationchange.kveFeatured',syncPlaylistHeight);if(window.ResizeObserver){var ro=new ResizeObserver(syncPlaylistHeight);var main=g.find('.kve-featured-main')[0];if(main)ro.observe(main);}}g.on('click','.kve-gallery-next',function(){if(track[0])track[0].scrollBy({left:track.innerWidth()*(g.data('layout')==='slider'?1:0.8),behavior:'smooth'});});g.on('click','.kve-gallery-prev',function(){if(track[0])track[0].scrollBy({left:-track.innerWidth()*(g.data('layout')==='slider'?1:0.8),behavior:'smooth'});});g.on('click','.kve-gallery-dot',function(){var i=parseInt($(this).data('index'),10)||0,item=track.children().eq(i);if(item.length&&track[0])track[0].scrollTo({left:item.position().left+track.scrollLeft(),behavior:'smooth'});g.find('.kve-gallery-dot').removeClass('is-active').eq(i).addClass('is-active');});if(track.length&&g.data('autoplay')===1){var timer=setInterval(function(){if(document.hidden)return;var max=track[0].scrollWidth-track.innerWidth();if(max<=2)return;var next=track.scrollLeft()+track.innerWidth()*(g.data('layout')==='slider'?1:.8);if(next>=max-3)next=0;track[0].scrollTo({left:next,behavior:'smooth'});},ivl);g.on('mouseenter focusin',function(){clearInterval(timer)});g.on('mouseleave focusout',function(){timer=setInterval(function(){var max=track[0].scrollWidth-track.innerWidth();var next=track.scrollLeft()+track.innerWidth()*(g.data('layout')==='slider'?1:.8);track[0].scrollTo({left:next>=max-3?0:next,behavior:'smooth'});},ivl)});}g.on('scroll','.kve-gallery-track',function(){var items=track.children(),left=track.scrollLeft(),best=0,dist=Infinity;items.each(function(i){var d=Math.abs(this.offsetLeft-track[0].offsetLeft-left);if(d<dist){dist=d;best=i;}});g.find('.kve-gallery-dot').removeClass('is-active').eq(best).addClass('is-active');});g.on('click','.kve-featured-choice',function(){var b=$(this),i=b.data('index');g.find('.kve-featured-choice').removeClass('is-active').attr('aria-pressed','false');b.addClass('is-active').attr('aria-pressed','true');g.find('.kve-featured-player').prop('hidden',true).removeClass('is-active');g.find('.kve-featured-player[data-index="'+i+'"]').prop('hidden',false).addClass('is-active');syncPlaylistHeight();});});});
KVEJS;
        wp_add_inline_script('jquery-core', $gallery_js);
    }
    public function admin_assets($hook) {
        if (strpos((string)$hook, 'kve-') === false) return;
        wp_enqueue_style('kve-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', array(), self::VERSION);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page routing; this value does not trigger a state change.
        if (!isset($_GET['page'])) return;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page routing; sanitized below and used only to enqueue assets.
        $page = sanitize_key(wp_unslash($_GET['page']));
        if ($page === 'kve-collections') wp_enqueue_style('kve-frontend', plugin_dir_url(__FILE__) . 'assets/frontend.css', array(), self::VERSION);
        if ($page === 'kve-videos') {
            wp_enqueue_script('jquery');
            $library_js = <<<'KVELIBRARYJS'
jQuery(function($){var table=$('.kve-video-library-table');if(!table.length)return;var rows=table.find('tbody tr');var bar=$('<div class="kve-library-tools"><label for="kve-library-search">Search videos</label> <input type="search" id="kve-library-search" class="regular-text" placeholder="Search title, URL or shortcode…"> <label for="kve-library-sort">Sort</label> <select id="kve-library-sort"><option value="title-asc">Title A–Z</option><option value="title-desc">Title Z–A</option><option value="newest">Newest first</option></select></div>');table.before(bar);$('#kve-library-search').on('input',function(){var q=$(this).val().toLowerCase();rows.each(function(){$(this).toggle($(this).text().toLowerCase().indexOf(q)!==-1);});});$('#kve-library-sort').on('change',function(){var v=$(this).val(),sorted=rows.get().sort(function(a,b){var A=$(a).find('td').eq(0).text().toLowerCase(),B=$(b).find('td').eq(0).text().toLowerCase();if(v==='title-asc')return A.localeCompare(B);if(v==='title-desc')return B.localeCompare(A);return $(b).data('created')-$(a).data('created');});$.each(sorted,function(_,r){table.find('tbody').append(r);});rows=table.find('tbody tr');});table.on('click','.kve-copy-shortcode',function(){var b=$(this),txt=b.data('shortcode');var t=$('<textarea>').val(txt).appendTo('body').select();var ok=document.execCommand('copy');t.remove();b.text(ok?'Copied':'Copy failed');setTimeout(function(){b.text('Copy')},1200);});});
KVELIBRARYJS;
            wp_add_inline_script('jquery', $library_js, 'after');
        }
        if ($page === 'kve-collections') {
            wp_enqueue_script('kve-collection-generator', plugin_dir_url(__FILE__) . 'assets/collection-generator.js', array(), self::VERSION, true);
        }
    }
    private function header($subtitle = '') {
        echo '<div class="wrap kve-wrap"><header class="kve-header"><div class="kve-brand"><img src="'.esc_url(plugin_dir_url(__FILE__).'assets/kashida-mark.svg').'" alt=""><div><h1>'.esc_html__('Kashida Video Embed','kashida-video-embed').'</h1><p>'.esc_html($subtitle ?: __('Paste a video URL. We handle the embed.','kashida-video-embed')).'</p></div></div><span class="kve-version">v'.esc_html(self::VERSION).'</span></header>';
    }
    private function footer() { /* translators: %s is the linked name of the plugin developer. */ echo '<footer class="kve-footer"><img src="'.esc_url(plugin_dir_url(__FILE__).'assets/kashida-logo.svg').'" alt="Kashida"><span>'.wp_kses_post(sprintf(__('Kashida Media Services by %s','kashida-video-embed'), '<a href="https://kashida.ly" target="_blank" rel="noopener noreferrer">Mrwan elGobee</a>')).'</span></footer></div>'; }
    public function videos_page() {
        if (!current_user_can('edit_posts')) return;
        $this->header(__('Manage videos and reuse them anywhere on your site.','kashida-video-embed'));
        echo '<div class="kve-toolbar"><a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=kve-add-new')).'">'.esc_html__('Add New Video','kashida-video-embed').'</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=kve-help')).'">'.esc_html__('Open Help Guide','kashida-video-embed').'</a></div>';
        $items = get_posts(array('post_type'=>self::CPT,'post_status'=>'publish','numberposts'=>-1,'orderby'=>'date','order'=>'DESC'));
        echo '<div class="kve-card"><h2>'.esc_html__('Video Library','kashida-video-embed').'</h2><p>'.esc_html__('Search your saved videos, copy a shortcode, edit settings or delete an item.','kashida-video-embed').'</p><table class="widefat striped kve-video-library-table"><thead><tr><th>'.esc_html__('Title','kashida-video-embed').'</th><th>'.esc_html__('Video URL','kashida-video-embed').'</th><th>'.esc_html__('Shortcode','kashida-video-embed').'</th><th>'.esc_html__('Actions','kashida-video-embed').'</th></tr></thead><tbody>';
        if (!$items) echo '<tr><td colspan="4">'.esc_html__('No videos yet. Add your first video using its URL.','kashida-video-embed').'</td></tr>';
        foreach ($items as $item) {
            $url=get_post_meta($item->ID,'_kve_url',true);
            $shortcode='[kashida_video_embed id="'.absint($item->ID).'"]';
            $delete_url=wp_nonce_url(add_query_arg(array('action'=>'kve_delete_video','video_id'=>absint($item->ID)),admin_url('admin-post.php')),'kve_delete_video_'.absint($item->ID));
            echo '<tr data-created="'.esc_attr((string)get_post_time('U',true,$item)).'"><td>'.esc_html($item->post_title).'</td><td><a href="'.esc_url($url). '" target="_blank" rel="noopener noreferrer">'.esc_html(wp_trim_words($url,8,'…')).'</a></td><td><code>'.esc_html($shortcode).'</code> <button type="button" class="button button-small kve-copy-shortcode" data-shortcode="'.esc_attr($shortcode).'">'.esc_html__('Copy','kashida-video-embed').'</button></td><td><a href="'.esc_url(add_query_arg(array('page'=>'kve-add-new','edit'=>absint($item->ID)),admin_url('admin.php'))).'">'.esc_html__('Edit','kashida-video-embed').'</a> | <a class="kve-delete-video" href="'.esc_url($delete_url).'" onclick="return confirm(&quot;Delete this video? Shortcodes using it will stop displaying. This action cannot be undone.&quot;)">'.esc_html__('Delete','kashida-video-embed').'</a></td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<div class="kve-card"><h2>'.esc_html__('Next step','kashida-video-embed').'</h2><p>'.esc_html__('Use Video Collections to select saved videos and generate a collection shortcode.','kashida-video-embed').'</p><p><a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=kve-collections')).'">'.esc_html__('Open Video Collections','kashida-video-embed').'</a></p></div>';
        $this->footer();
    }

    private function d_attr_layouts($l) { return ' data-layouts="'.esc_attr($l).'"'; }
    private function d_toggle($id,$label,$on,$layouts) { return '<label class="kve-d-row kve-d-toggle"'.$this->d_attr_layouts($layouts).'><span>'.esc_html($label).'</span><input type="checkbox" id="'.esc_attr($id).'" '.checked($on,true,false).'><i class="kve-d-switch" aria-hidden="true"></i></label>'; }
    private function d_range($id,$label,$min,$max,$val,$unit,$layouts) { return '<div class="kve-d-row kve-d-range"'.$this->d_attr_layouts($layouts).'><div class="kve-d-rowhead"><label for="'.esc_attr($id).'">'.esc_html($label).'</label><output for="'.esc_attr($id).'" data-unit="'.esc_attr($unit).'">'.esc_html($val.$unit).'</output></div><input type="range" id="'.esc_attr($id).'" min="'.(int)$min.'" max="'.(int)$max.'" value="'.(int)$val.'"></div>'; }
    private function d_stepper($id,$label,$min,$max,$val) { return '<div class="kve-d-row kve-d-stepper-row"><span>'.esc_html($label).'</span><div class="kve-d-stepper" data-min="'.(int)$min.'" data-max="'.(int)$max.'"><button type="button" data-step="-1" aria-label="'.esc_attr__('Decrease','kashida-video-embed').'">&minus;</button><input type="text" id="'.esc_attr($id).'" value="'.(int)$val.'" readonly><button type="button" data-step="1" aria-label="'.esc_attr__('Increase','kashida-video-embed').'">+</button></div></div>'; }
    private function d_color($id,$label,$val,$layouts,$label_alt='') { return '<div class="kve-d-row kve-d-color"'.$this->d_attr_layouts($layouts).'><label for="'.esc_attr($id).'" data-alt="'.esc_attr($label_alt).'" data-base="'.esc_attr($label).'">'.esc_html($label).'</label><span class="kve-d-colorwrap"><input type="text" class="kve-d-hex" data-for="'.esc_attr($id).'" maxlength="7" value="'.esc_attr(strtoupper($val)).'" spellcheck="false"><input type="color" id="'.esc_attr($id).'" value="'.esc_attr($val).'"></span></div>'; }
    private function d_group($title,$layouts,$body,$alt_titles='') { return '<section class="kve-d-group"'.$this->d_attr_layouts($layouts).($alt_titles?' data-alt-titles="'.esc_attr($alt_titles).'"':'').'><h5>'.esc_html($title).'</h5>'.$body.'</section>'; }
    private function render_designer($editing) {
        $new = empty($editing);
        $g = function($k,$n,$o=null) use ($editing,$new) { if (isset($editing[$k]) && $editing[$k] !== '') return $editing[$k]; return $new ? $n : ($o===null ? $n : $o); };
        $layout = $g('layout','grid');
        $dc = (int)$g('desktop_columns',4,$g('columns',3));
        $tc = (int)$g('tablet_columns',2); $mc = (int)$g('mobile_columns',1);
        $gap = (int)$g('gap',20,16); $radius = (int)$g('radius',12,14); $tsize = (int)$g('title_size',16);
        $surface = $g('surface_color','#ffffff'); $text = $g('text_color','#1b2340');
        $overlay = $g('overlay','#0f1530','#000000'); $opacity = (int)$g('overlay_opacity',45,0);
        $sh = (int)$g('slider_height',440,420); $fit = $g('slider_fit','cover'); $ivl = (int)$g('interval',4,5);
        $ph = (int)$g('playlist_height',420); if ($ph < 240) $ph = 420;
        $scroll = $g('scrolling','yes')==='yes'; $active = $g('active_color','#2f6bd8','#1769aa'); $playlist_border = $g('playlist_border_color','#d7e2f0'); $playlist_icon = $g('playlist_icon_color','#2f6bd8'); $slider_container = $g('slider_container_color','#0b1220'); $slider_title_color = $g('slider_title_color','#ffffff');
        $meta = $g('show_metadata','yes','no')==='yes'; $titles = $g('show_title','yes')==='yes';
        $arrows = $g('show_arrows','yes')==='yes'; $dots = $g('show_dots','yes')==='yes'; $auto = $g('autoplay','no')==='yes';
        $tw = (int)$g('thumb_width',300); $lmode = $g('list_mode','details');
        $tabs = array(
            'grid'=>array('Grid',__('Responsive card grid','kashida-video-embed'),'dashicons-grid-view'),
            'list'=>array('List',__('Vertical rows with info','kashida-video-embed'),'dashicons-list-view'),
            'carousel'=>array('Carousel',__('Horizontal scrolling strip','kashida-video-embed'),'dashicons-slides'),
            'slider'=>array('Slider',__('Full-width hero slides','kashida-video-embed'),'dashicons-images-alt2'),
            'featured_playlist'=>array(__('Featured + Playlist','kashida-video-embed'),__('Player with side playlist','kashida-video-embed'),'dashicons-format-video'),
        );
        $o = '<section class="kve-generator-step"><h3>'.esc_html__('Step 2 — Customize display','kashida-video-embed').'</h3>';
        $o .= '<div class="kve-designer" data-layout="'.esc_attr($layout).'">';
        $o .= '<input type="hidden" id="kve-gallery-layout" value="'.esc_attr($layout).'"><input type="hidden" id="kve-gallery-columns" value="'.$dc.'"><input type="hidden" id="kve-gallery-list-mode" value="'.esc_attr($lmode).'"><input type="checkbox" id="kve-gallery-thumbnails" checked hidden>';
        $o .= '<div class="kve-d-top"><div><strong>'.esc_html__('Layout designer','kashida-video-embed').'</strong><span>'.esc_html__('Choose a layout, then fine-tune it. The preview uses the same styles as your website.','kashida-video-embed').'</span></div><button type="button" class="button" id="kve-d-reset">'.esc_html__('Reset settings','kashida-video-embed').'</button></div>';
        $o .= '<div class="kve-d-layout"><nav class="kve-d-tabs" aria-label="'.esc_attr__('Layout','kashida-video-embed').'">';
        foreach ($tabs as $key=>$t) $o .= '<button type="button" class="kve-d-tab'.($key===$layout?' is-active':'').'" data-layout="'.esc_attr($key).'" aria-pressed="'.($key===$layout?'true':'false').'"><span class="dashicons '.esc_attr($t[2]).'"></span><span><strong>'.esc_html($t[0]).'</strong><small>'.esc_html($t[1]).'</small></span></button>';
        $o .= '</nav><div class="kve-d-stage"><div class="kve-d-stagehead"><div><strong id="kve-d-stage-title"></strong><small>'.esc_html__('Live preview','kashida-video-embed').' · <span id="kve-d-count"></span></small></div><code id="kve-d-code"></code></div><div class="kve-d-canvas"><div id="kve-preview-mount"></div></div></div>';
        $o .= '<aside class="kve-d-panel"><h4>'.esc_html__('Display settings','kashida-video-embed').'</h4>';
        $o .= $this->d_group(__('Responsive columns','kashida-video-embed'),'grid carousel',$this->d_stepper('kve-gallery-desktop-columns',__('Desktop','kashida-video-embed'),1,6,$dc).$this->d_stepper('kve-gallery-tablet-columns',__('Tablet','kashida-video-embed'),1,4,$tc).$this->d_stepper('kve-gallery-mobile-columns',__('Mobile','kashida-video-embed'),1,2,$mc),'carousel='.__('Visible items','kashida-video-embed'));
        $sel = '<div class="kve-d-row kve-d-select" data-layouts="slider"><label for="kve-gallery-slider-fit">'.esc_html__('Image fit','kashida-video-embed').'</label><select id="kve-gallery-slider-fit"><option value="cover" '.selected($fit,'cover',false).'>'.esc_html__('Cover (crop)','kashida-video-embed').'</option><option value="contain" '.selected($fit,'contain',false).'>'.esc_html__('Contain (fit)','kashida-video-embed').'</option></select></div>';
        $o .= $this->d_group(__('Slide','kashida-video-embed'),'slider',$this->d_range('kve-gallery-slider-height',__('Height','kashida-video-embed'),180,1000,$sh,'px','slider').$sel.$this->d_color('kve-gallery-slider-container',__('Slider container background','kashida-video-embed'),$slider_container,'slider').$this->d_color('kve-gallery-slider-title-color',__('Title color','kashida-video-embed'),$slider_title_color,'slider')); 
        $o .= $this->d_group(__('Playlist','kashida-video-embed'),'featured_playlist',$this->d_range('kve-gallery-playlist-height',__('Playlist height','kashida-video-embed'),240,800,$ph,'px','featured_playlist').$this->d_toggle('kve-gallery-scrolling',__('Enable scrolling','kashida-video-embed'),$scroll,'featured_playlist').$this->d_color('kve-gallery-active',__('Active item color','kashida-video-embed'),$active,'featured_playlist').$this->d_color('kve-gallery-playlist-border',__('Playlist border color','kashida-video-embed'),$playlist_border,'featured_playlist').$this->d_color('kve-gallery-playlist-icon',__('Playlist icon color','kashida-video-embed'),$playlist_icon,'featured_playlist'));
        $o .= $this->d_group(__('Playback','kashida-video-embed'),'carousel slider',$this->d_toggle('kve-gallery-autoplay',__('Autoplay','kashida-video-embed'),$auto,'carousel slider').$this->d_range('kve-gallery-interval',__('Interval','kashida-video-embed'),2,12,$ivl,'s','carousel slider'));
        $o .= $this->d_group(__('Navigation','kashida-video-embed'),'carousel slider',$this->d_toggle('kve-gallery-arrows',__('Show arrows','kashida-video-embed'),$arrows,'carousel slider').$this->d_toggle('kve-gallery-dots',__('Show pagination dots','kashida-video-embed'),$dots,'carousel slider'));
        $o .= $this->d_group(__('Content','kashida-video-embed'),'grid list carousel slider featured_playlist',$this->d_toggle('kve-gallery-info',__('Show info column','kashida-video-embed'),$lmode==='details','list').$this->d_toggle('kve-gallery-show-title',__('Show video titles','kashida-video-embed'),$titles,'grid carousel slider featured_playlist').$this->d_toggle('kve-gallery-metadata',__('Show metadata','kashida-video-embed'),$meta,'grid list carousel slider featured_playlist'));
        $o .= $this->d_group(__('Row appearance','kashida-video-embed'),'list',$this->d_range('kve-gallery-thumb-width',__('Thumbnail width','kashida-video-embed'),160,480,$tw,'px','list'));
        $o .= $this->d_group(__('Appearance','kashida-video-embed'),'grid list carousel slider featured_playlist',
            $this->d_color('kve-gallery-surface',__('Card background','kashida-video-embed'),$surface,'grid list carousel slider featured_playlist',__('Row background','kashida-video-embed')).
            $this->d_color('kve-gallery-text',__('Title color','kashida-video-embed'),$text,'grid list carousel featured_playlist').
            $this->d_color('kve-gallery-overlay',__('Overlay color','kashida-video-embed'),$overlay,'carousel slider').
            $this->d_range('kve-gallery-opacity',__('Overlay opacity','kashida-video-embed'),0,100,$opacity,'%','carousel slider').
            $this->d_range('kve-gallery-title-size',__('Title size','kashida-video-embed'),10,40,$tsize,'px','grid list carousel slider featured_playlist').
            $this->d_range('kve-gallery-gap',__('Spacing','kashida-video-embed'),0,48,$gap,'px','grid list carousel featured_playlist').
            $this->d_range('kve-gallery-radius',__('Border radius','kashida-video-embed'),0,32,$radius,'px','grid list carousel slider featured_playlist'));
        $o .= '</aside></div></div></section>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $o is assembled from escaped form fields and HTML returned by the plugin's renderer.
        echo $o;
    }
    public function collections_page() {
        if (!current_user_can('edit_posts')) return;
        $collections = get_option('kve_saved_collections', array());
        if (!is_array($collections)) $collections = array();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only edit selection; saving still requires a separate verified form nonce.
        $edit_id = isset($_GET['edit_collection']) ? absint($_GET['edit_collection']) : 0;
        $editing = ($edit_id && isset($collections[$edit_id])) ? $collections[$edit_id] : array();
        $selected = isset($editing['ids']) && is_array($editing['ids']) ? array_map('absint', $editing['ids']) : array();
        $this->header(__('Create and manage reusable video collections.','kashida-video-embed'));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only status flag after a nonce-protected POST/redirect.
        if (isset($_GET['saved_collection'])) echo '<div class="notice notice-success is-dismissible"><p>'.esc_html__('Collection saved. Its shortcode stays the same when you edit the collection.','kashida-video-embed').'</p></div>';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only status flag after a nonce-protected action.
        if (isset($_GET['deleted_collection'])) echo '<div class="notice notice-success is-dismissible"><p>'.esc_html__('Collection deleted.','kashida-video-embed').'</p></div>';
        echo '<div class="kve-card"><h2>'.esc_html__('Saved collections','kashida-video-embed').'</h2><p>'.esc_html__('Edit a collection to change its videos or design without changing the shortcode already used on your pages.','kashida-video-embed').'</p>';
        if ($collections) {
            echo '<table class="widefat striped"><thead><tr><th>'.esc_html__('Collection','kashida-video-embed').'</th><th>'.esc_html__('Videos','kashida-video-embed').'</th><th>'.esc_html__('Shortcode','kashida-video-embed').'</th><th>'.esc_html__('Actions','kashida-video-embed').'</th></tr></thead><tbody>';
            foreach ($collections as $cid=>$c) {
                $sc='[kashida_video_collection id="'.absint($cid).'"]';
                $delete=wp_nonce_url(add_query_arg(array('action'=>'kve_delete_collection','collection_id'=>absint($cid)),admin_url('admin-post.php')),'kve_delete_collection_'.absint($cid));
                echo '<tr><td>'.esc_html($c['name'] ?? ('Collection '.absint($cid))).'</td><td>'.count($c['ids'] ?? array()).'</td><td><code>'.esc_html($sc).'</code></td><td><a class="button button-small" href="'.esc_url(add_query_arg(array('page'=>'kve-collections','edit_collection'=>absint($cid)),admin_url('admin.php'))).'">'.esc_html__('Edit','kashida-video-embed').'</a> <a class="button button-small kve-copy-shortcode" data-shortcode="'.esc_attr($sc).'" href="#">'.esc_html__('Copy shortcode','kashida-video-embed').'</a> <a class="button button-small" onclick="return confirm(\''.esc_js(__('Delete this collection? Existing shortcodes will no longer display it.','kashida-video-embed')).'\')" href="'.esc_url($delete).'">'.esc_html__('Delete','kashida-video-embed').'</a></td></tr>';
            }
            echo '</tbody></table>';
        } else echo '<p>'.esc_html__('No saved collections yet. Create your first collection below.','kashida-video-embed').'</p>';
        echo '</div>';
        $items = get_posts(array('post_type'=>self::CPT,'post_status'=>'publish','numberposts'=>-1,'orderby'=>'date','order'=>'DESC'));
        echo '<div class="kve-card kve-gallery-generator"><h2>'.($edit_id ? esc_html__('Edit collection','kashida-video-embed') : esc_html__('Create a collection','kashida-video-embed')).'</h2>';
        echo '<p>'.esc_html__('Select videos, customize the layout, save the collection, then copy its permanent shortcode.','kashida-video-embed').'</p>';
        echo '<label class="kve-collection-name-label">'.esc_html__('Collection name','kashida-video-embed').'<input type="text" id="kve-collection-name" class="regular-text" value="'.esc_attr($editing['name'] ?? '').'" placeholder="'.esc_attr__('e.g. Tutorials','kashida-video-embed').'" required></label>';
        if ($items) {
            echo '<section class="kve-generator-step"><h3>'.esc_html__('Step 1 — Select videos','kashida-video-embed').'</h3><div class="kve-selection-actions"><button type="button" class="button" id="kve-select-all">'.esc_html__('Select all','kashida-video-embed').'</button> <button type="button" class="button" id="kve-clear-selection">'.esc_html__('Clear selection','kashida-video-embed').'</button><span id="kve-selection-count">'.esc_html__('No videos selected','kashida-video-embed').'</span></div><div class="kve-gallery-pick-list">';
            foreach ($items as $item) echo '<label><input type="checkbox" class="kve-gallery-pick" value="'.absint($item->ID).'" '.checked(in_array((int)$item->ID,$selected,true),true,false).'> '.esc_html($item->post_title).' <span>#'.absint($item->ID).'</span></label>';
            echo '</div></section>';
            $this->render_designer($editing);
            echo '<section class="kve-generator-step"><h3>'.esc_html__('Step 3 — Save and copy','kashida-video-embed').'</h3><p><button type="button" class="button button-primary" id="kve-gallery-generate">'.esc_html__('Generate shortcode','kashida-video-embed').'</button> <span id="kve-generator-status" role="status">'.esc_html__('Choose videos and options, then generate the collection settings.','kashida-video-embed').'</span></p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" id="kve-save-collection-form"><input type="hidden" name="action" value="kve_save_collection"><input type="hidden" name="collection_id" value="'.absint($edit_id).'">';
            wp_nonce_field('kve_save_collection','kve_collection_nonce');
            echo '<input type="hidden" name="collection_name" id="kve-save-name"><input type="hidden" name="collection_data" id="kve-save-data"><button type="submit" class="button button-primary" id="kve-save-collection" disabled>'.($edit_id ? esc_html__('Update collection','kashida-video-embed') : esc_html__('Save collection','kashida-video-embed')).'</button> <label for="kve-gallery-output">'.esc_html__('Permanent shortcode','kashida-video-embed').'</label><div class="kve-url-row"><input id="kve-gallery-output" class="large-text" readonly disabled placeholder="'.esc_attr__('Save the collection to get its permanent shortcode.','kashida-video-embed').'"/><button type="button" class="button" id="kve-gallery-copy" disabled>'.esc_html__('Copy shortcode','kashida-video-embed').'</button></div></form><p class="description">'.esc_html__('The collection shortcode uses a stable ID. Editing the saved collection updates its display without changing the shortcode already pasted into pages.','kashida-video-embed').'</p></section>';
        } else echo '<p>'.esc_html__('Add videos to the library before creating a collection.','kashida-video-embed').'</p><p><a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=kve-add-new')).'">'.esc_html__('Add New Video','kashida-video-embed').'</a></p>';
        echo '</div>';
        echo '<script>window.kveEditingCollection='.wp_json_encode(array('id'=>$edit_id,'data'=>$editing)).';</script>';
        echo '<script>document.addEventListener("click",function(e){var b=e.target.closest(".kve-copy-shortcode");if(!b)return;e.preventDefault();var t=document.createElement("textarea");t.value=b.dataset.shortcode;document.body.appendChild(t);t.select();try{document.execCommand("copy");b.textContent="Copied";}catch(err){b.textContent="Select and copy";}t.remove();});</script>';
        $this->footer();
    }
    public function save_collection() {
        if (!current_user_can('edit_posts') || !isset($_POST['kve_collection_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kve_collection_nonce'])),'kve_save_collection')) wp_die(esc_html__('You are not allowed to save this collection.','kashida-video-embed'));
        // This field is JSON, so sanitizing the raw JSON string could corrupt its structure. The nonce is verified above; every decoded field is validated/sanitized below before storage.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is decoded and each field is allow-listed or sanitized before use.
        $collection_json = isset($_POST['collection_data']) ? wp_unslash($_POST['collection_data']) : '';
        $data_raw = $collection_json !== '' ? json_decode($collection_json, true, 32) : null;
        if (!is_array($data_raw)) wp_die(esc_html__('Invalid collection settings. Please generate the settings again.','kashida-video-embed'));
        $ids = array_values(array_unique(array_filter(array_map('absint',(array)($data_raw['ids'] ?? array())))));
        $valid = array(); foreach($ids as $id){$p=get_post($id);if($p && $p->post_type===self::CPT && $p->post_status==='publish')$valid[]=$id;}
        if (!$valid) wp_die(esc_html__('Select at least one saved video.','kashida-video-embed'));
        $name = sanitize_text_field(wp_unslash($_POST['collection_name'] ?? ''));
        if ($name==='') $name = __('Video collection','kashida-video-embed');
        $collections = get_option('kve_saved_collections',array()); if(!is_array($collections))$collections=array();
        $id=absint($_POST['collection_id'] ?? 0);
        if(!$id || !isset($collections[$id])) { $id=(int)get_option('kve_next_collection_id',1); if($id<1)$id=1; update_option('kve_next_collection_id',$id+1,false); }
        $collections[$id]=array('name'=>$name,'ids'=>$valid,'layout'=>in_array(($data_raw['layout']??''),array('grid','list','carousel','slider','featured_playlist'),true)?$data_raw['layout']:'grid','columns'=>max(1,min(5,absint($data_raw['columns']??3))),'gap'=>max(0,min(48,absint($data_raw['gap']??16))),'overlay'=>sanitize_hex_color($data_raw['overlay']??'')?:'#000000','overlay_opacity'=>max(0,min(100,absint($data_raw['overlay_opacity']??0))),'text_color'=>sanitize_hex_color($data_raw['text_color']??'')?:'#ffffff','title_size'=>max(10,min(40,absint($data_raw['title_size']??16))),'show_title'=>!empty($data_raw['show_title'])?'yes':'no','surface_color'=>sanitize_hex_color($data_raw['surface_color']??'')?:'#ffffff','show_arrows'=>!empty($data_raw['show_arrows'])?'yes':'no','show_dots'=>!empty($data_raw['show_dots'])?'yes':'no','autoplay'=>!empty($data_raw['autoplay'])?'yes':'no','thumbnails'=>!empty($data_raw['thumbnails'])?'yes':'no','desktop_columns'=>max(1,min(6,absint($data_raw['desktop_columns']??($data_raw['columns']??3)))),'tablet_columns'=>max(1,min(4,absint($data_raw['tablet_columns']??2))),'mobile_columns'=>max(1,min(2,absint($data_raw['mobile_columns']??1))),'slider_height'=>max(180,min(1000,absint($data_raw['slider_height']??420))),'list_mode'=>in_array(($data_raw['list_mode']??'details'),array('video_only','details'),true)?$data_raw['list_mode']:'details','ratio'=>'16:9','radius'=>max(0,min(32,absint($data_raw['radius']??14))),'thumb_width'=>max(160,min(480,absint($data_raw['thumb_width']??300))),'interval'=>max(2,min(12,absint($data_raw['interval']??5))),'slider_fit'=>(($data_raw['slider_fit']??'')==='contain')?'contain':'cover','playlist_height'=>max(240,min(800,absint($data_raw['playlist_height']??420))),'scrolling'=>!empty($data_raw['scrolling'])?'yes':'no','active_color'=>sanitize_hex_color($data_raw['active_color']??'')?:'#2f6bd8','playlist_border_color'=>sanitize_hex_color($data_raw['playlist_border_color']??'')?:'#d7e2f0','playlist_icon_color'=>sanitize_hex_color($data_raw['playlist_icon_color']??'')?:'#2f6bd8','slider_container_color'=>sanitize_hex_color($data_raw['slider_container_color']??'')?:'#0b1220','slider_title_color'=>sanitize_hex_color($data_raw['slider_title_color']??'')?:'#ffffff','show_metadata'=>!empty($data_raw['show_metadata'])?'yes':'no');
        update_option('kve_saved_collections',$collections,false);
        wp_safe_redirect(add_query_arg(array('page'=>'kve-collections','edit_collection'=>$id,'saved_collection'=>1),admin_url('admin.php')));exit;
    }
    public function delete_collection() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read only to construct the item-specific nonce action; check_admin_referer() validates the request below.
        $id = isset($_GET['collection_id']) ? absint($_GET['collection_id']) : 0;
        if(!current_user_can('edit_posts') || !$id || !check_admin_referer('kve_delete_collection_'.$id)) wp_die(esc_html__('You are not allowed to delete this collection.','kashida-video-embed'));
        $collections=get_option('kve_saved_collections',array()); if(is_array($collections)&&isset($collections[$id])){unset($collections[$id]);update_option('kve_saved_collections',$collections,false);}
        wp_safe_redirect(add_query_arg(array('page'=>'kve-collections','deleted_collection'=>1),admin_url('admin.php')));exit;
    }
    public function saved_collection_shortcode($atts) {
        $atts=shortcode_atts(array('id'=>0),(array)$atts,'kashida_video_collection'); $id=absint($atts['id']);
        $collections=get_option('kve_saved_collections',array()); if(!$id||!is_array($collections)||!isset($collections[$id]))return '';
        $c=$collections[$id];
        return $this->gallery_shortcode(array('ids'=>implode(',',(array)$c['ids']),'layout'=>$c['layout']??'grid','columns'=>$c['columns']??3,'gap'=>$c['gap']??16,'overlay'=>$c['overlay']??'#000000','overlay_opacity'=>$c['overlay_opacity']??0,'text_color'=>$c['text_color']??'#ffffff','title_size'=>$c['title_size']??16,'show_title'=>$c['show_title']??'yes','surface_color'=>$c['surface_color']??'#ffffff','show_arrows'=>$c['show_arrows']??'yes','show_dots'=>$c['show_dots']??'yes','autoplay'=>$c['autoplay']??'no','thumbnails'=>$c['thumbnails']??'yes','desktop_columns'=>$c['desktop_columns']??($c['columns']??3),'tablet_columns'=>$c['tablet_columns']??2,'mobile_columns'=>$c['mobile_columns']??1,'slider_height'=>$c['slider_height']??420,'list_mode'=>$c['list_mode']??'details','ratio'=>$c['ratio']??'16:9','radius'=>$c['radius']??14,'thumb_width'=>$c['thumb_width']??300,'interval'=>$c['interval']??5,'slider_fit'=>$c['slider_fit']??'cover','playlist_height'=>$c['playlist_height']??0,'scrolling'=>$c['scrolling']??'yes','active_color'=>$c['active_color']??'#1769aa','playlist_border_color'=>$c['playlist_border_color']??'#d7e2f0','playlist_icon_color'=>$c['playlist_icon_color']??'#2f6bd8','slider_container_color'=>$c['slider_container_color']??'#0b1220','slider_title_color'=>$c['slider_title_color']??'#ffffff','show_metadata'=>$c['show_metadata']??'legacy'));
    }
    public function add_page() {
        if (!current_user_can('edit_posts')) return;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only edit selection; all writes use the nonce-protected save handler.
        $id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
        $item = $id ? get_post($id) : null;
        if ($item && ($item->post_type !== self::CPT || !current_user_can('edit_post',$id))) $item = null;
        $url = $item ? get_post_meta($id,'_kve_url',true) : '';
        $ratio = $item ? get_post_meta($id,'_kve_ratio',true) : '16:9';
        $controls = $item ? (get_post_meta($id,'_kve_controls',true) !== '0') : true;
        $lazy = $item ? (get_post_meta($id,'_kve_lazy',true) !== '0') : true;
        $input_mode = $item ? (get_post_meta($id,'_kve_input_mode',true) ?: 'url') : 'url';
        $display_mode = $item ? (get_post_meta($id,'_kve_display_mode',true) ?: 'player') : 'player';
        $embed_code = $item ? get_post_meta($id,'_kve_embed_code',true) : '';
        $dailymotion_player_id = $item ? get_post_meta($id,'_kve_dailymotion_player_id',true) : '';
        $this->header(__('Add a video using its regular share URL.','kashida-video-embed'));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only status flag after a nonce-protected POST/redirect.
        if (isset($_GET['saved'])) echo '<div class="notice notice-success is-dismissible"><p>'.esc_html__('Video saved. You can continue editing its settings below.','kashida-video-embed').'</p></div>';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only status flag after a nonce-protected action.
        if (isset($_GET['deleted'])) echo '<div class="notice notice-success is-dismissible"><p>'.esc_html__('Video deleted successfully.','kashida-video-embed').'</p></div>';
        echo '<div class="kve-card"><h2>'.esc_html($item ? __('Edit Video','kashida-video-embed') : __('Add New Video','kashida-video-embed')).'</h2><p>'.esc_html__('Paste a video URL or provider embed code. Settings are applied to the preview below.','kashida-video-embed').'</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('kve_save_video'); echo '<input type="hidden" name="action" value="kve_save_video"><input type="hidden" name="video_id" value="'.absint($id).'">';
        echo '<div class="kve-settings-grid"><div class="kve-settings-column">';
        echo '<label for="kve-title">'.esc_html__('Video title','kashida-video-embed').'</label><input class="large-text" id="kve-title" name="title" required value="'.esc_attr($item ? $item->post_title : '').'" placeholder="'.esc_attr__('e.g. Product introduction','kashida-video-embed').'">';
        echo '<label for="kve-input-mode">'.esc_html__('Source type','kashida-video-embed').'</label><select id="kve-input-mode" name="input_mode"><option value="url" '.selected($input_mode,'url',false).'>'.esc_html__('Video URL','kashida-video-embed').'</option><option value="code" '.selected($input_mode,'code',false).'>'.esc_html__('Embed code','kashida-video-embed').'</option></select><div id="kve-url-field"><label for="kve-url">'.esc_html__('Video URL','kashida-video-embed').'</label><input class="large-text" type="url" id="kve-url" name="url" value="'.esc_attr($url).'" placeholder="https://www.youtube.com/watch?v=…"><div id="kve-provider-help" class="kve-provider-help" aria-live="polite"><div class="kve-provider-brand"><span id="kve-provider-logo" class="kve-provider-logo">▶</span><strong id="kve-provider-name">'.esc_html__('Paste a video URL to see platform instructions','kashida-video-embed').'</strong></div><div id="kve-provider-instructions" class="kve-provider-instructions">'.esc_html__('Choose a platform URL. Instructions for copying its link or embed code will appear here.','kashida-video-embed').'</div><a id="kve-provider-doc" href="https://wordpress.org/documentation/article/wordpress-editor/" target="_blank" rel="noopener noreferrer" hidden>'.esc_html__('Open official help','kashida-video-embed').'</a></div><div id="kve-dailymotion-player-field"><label for="kve-dailymotion-player-id">'.esc_html__('Dailymotion Player ID (required for Dailymotion links)','kashida-video-embed').'</label><input class="large-text" type="text" id="kve-dailymotion-player-id" name="dailymotion_player_id" value="'.esc_attr($dailymotion_player_id).'" placeholder="e.g. x8lr5"></div></div><div id="kve-code-field"><label for="kve-embed-code">'.esc_html__('Embed code','kashida-video-embed').'</label><textarea class="large-text code" rows="5" id="kve-embed-code" name="embed_code" placeholder="&lt;iframe src=...&gt;&lt;/iframe&gt;">'.esc_textarea($embed_code).'</textarea></div>';
        echo '<label for="kve-display-mode">'.esc_html__('Display mode','kashida-video-embed').'</label><select id="kve-display-mode" name="display_mode"><option value="player" '.selected($display_mode,'player',false).'>'.esc_html__('Video only','kashida-video-embed').'</option><option value="provider" '.selected($display_mode,'provider',false).'>'.esc_html__('Show provider details and interface','kashida-video-embed').'</option></select></div><div class="kve-settings-column">';
        echo '<label for="kve-ratio">'.esc_html__('Aspect ratio','kashida-video-embed').'</label><select id="kve-ratio" name="ratio"><option '.selected($ratio,'16:9',false).' value="16:9">16:9</option><option '.selected($ratio,'9:16',false).' value="9:16">9:16</option><option '.selected($ratio,'4:3',false).' value="4:3">4:3</option><option '.selected($ratio,'1:1',false).' value="1:1">1:1</option></select><p><label class="kve-check-label"><input type="checkbox" name="controls" value="1" '.checked($controls,true,false).'> '.esc_html__('Show player controls when supported','kashida-video-embed').'</label></p><p><label class="kve-check-label"><input type="checkbox" name="lazy" value="1" '.checked($lazy,true,false).'> '.esc_html__('Enable lazy loading','kashida-video-embed').'</label></p><p class="description">'.esc_html__('Additional supported sources include Dailymotion, TikTok, Twitch, Instagram and WordPress.tv when their embed methods are available.','kashida-video-embed').'</p></div></div>';
        if ($id) { echo '<div class="kve-shortcode-box"><label for="kve-shortcode">'.esc_html__('Video shortcode','kashida-video-embed').'</label><div class="kve-url-row"><input readonly class="large-text code" id="kve-shortcode" value="'.esc_attr('[kashida_video_embed id="'.$id.'"]').'"><button type="button" class="button" id="kve-copy-shortcode">'.esc_html__('Copy shortcode','kashida-video-embed').'</button></div><p class="description">'.esc_html__('Copy this shortcode into any page or post to display this video.','kashida-video-embed').'</p></div>'; }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_video() validates URLs and sanitizes provider embed markup before returning HTML.
        echo '<h3>'.esc_html__('Video Preview','kashida-video-embed').'</h3><div class="kve-url-row"><button type="button" class="button" id="kve-preview">'.esc_html__('Refresh preview','kashida-video-embed').'</button></div><div id="kve-preview-area" class="kve-preview-area">'.($url ? $this->render_video($url,$ratio,$controls,$lazy,$input_mode,$display_mode,$embed_code,$dailymotion_player_id) : '<p>'.esc_html__('Your video preview will appear here when supported.','kashida-video-embed').'</p>').'</div><p><button class="button button-primary" type="submit">'.esc_html__('Save Video','kashida-video-embed').'</button></p></form></div>';
        echo '<script>document.addEventListener("DOMContentLoaded",function(){var mode=document.getElementById("kve-input-mode"),uf=document.getElementById("kve-url-field"),cf=document.getElementById("kve-code-field");function toggleMode(){uf.style.display=mode.value==="url"?"":"none";cf.style.display=mode.value==="code"?"":"none";}mode.addEventListener("change",toggleMode);toggleMode();var providerData={youtube:{name:"YouTube",mark:"▶",color:"#ff0033",steps:"Link: open the video, select Share, then Copy. Embed code: select Share → Embed → Copy. Paste either here.",url:"https://support.google.com/youtube/answer/57741"},vimeo:{name:"Vimeo",mark:"v",color:"#1ab7ea",steps:"Link: open the video and select Share → Copy link. Embed code: select Share → Embed, then Copy embed code. Embedding may be restricted by the owner.",url:"https://help.vimeo.com/hc/en-us/articles/12426259908881-How-to-embed-my-video"},facebook:{name:"Facebook",mark:"f",color:"#0866ff",steps:"Link: open the public video or Reel, use the menu or Share option, then Copy link. Embed code: when available for a public video, open its menu and choose Embed. Some videos, private posts and login-restricted content cannot be embedded.",url:"https://www.facebook.com/help/"},x:{name:"X",mark:"𝕏",color:"#111111",steps:"Link: open the post containing the video, select Share, then Copy link. Embed code: open the post menu (•••) and choose Embed post on the X publish tools. The official embed may show the post details, not only the video.",url:"https://publish.x.com/"},tiktok:{name:"TikTok",mark:"♪",color:"#111111",steps:"Link: open the video in a desktop browser, select Share → Copy link. Embed code: select Share → Embed and copy the code. TikTok embeds normally include creator details and links.",url:"https://developers.tiktok.com/docs/en/embed-videos"},instagram:{name:"Instagram",mark:"◎",color:"#c13584",steps:"Link: open the public post or Reel, select Share → Copy link. Embed code: open the post menu and choose Embed when available. Private-account content cannot be embedded for public viewing.",url:"https://help.instagram.com/"},dailymotion:{name:"Dailymotion",mark:"D",color:"#00aaff",steps:"Link: open the video and use Share / Copy link. Embed code: in Dailymotion Studio, go to Media → Videos, open the three-dot menu and choose Embed video, then copy the iframe or embed script.",url:"https://faq.dailymotion.com/hc/en-us/articles/115008959787-Embed-your-content"},twitch:{name:"Twitch",mark:"▣",color:"#9146ff",steps:"Link: use Share on a stream, past broadcast or clip and copy the URL. Embed code: use the Share / Embed option; Twitch embeds may require a parent-domain setting to be configured.",url:"https://help.twitch.tv/s/article/how-to-share-a-stream"},direct:{name:"Direct video / WordPress Media Library",mark:"▶",color:"#50575e",steps:"In WordPress, open Media → Library, select the video file, and copy its File URL. Paste the URL here. Direct links usually end in .mp4, .webm or .ogv.",url:"https://wordpress.org/documentation/article/media-library-screen/"},unknown:{name:"Other video website",mark:"↗",color:"#50575e",steps:"Paste the public share URL first. If automatic embedding is unavailable, switch Source type to Embed code and paste the iframe supplied by that website. The site must allow external embedding.",url:"https://wordpress.org/documentation/article/wordpress-embeds/"}};var urlInput=document.getElementById("kve-url"),pLogo=document.getElementById("kve-provider-logo"),pName=document.getElementById("kve-provider-name"),pSteps=document.getElementById("kve-provider-instructions"),pDoc=document.getElementById("kve-provider-doc");function updateProvider(sourceValue){var value=(typeof sourceValue==="string"?sourceValue:(urlInput?urlInput.value:" ")).trim(),host="";try{host=new URL(value).hostname.toLowerCase().replace(/^www\./,"");}catch(e){}var key="unknown";if(!host)key="empty";else if(host.indexOf("youtube.com")>=0||host==="youtu.be")key="youtube";else if(host.indexOf("vimeo.com")>=0)key="vimeo";else if(host.indexOf("facebook.com")>=0||host==="fb.watch"||host.indexOf("fb.watch")>=0)key="facebook";else if(host==="x.com"||host.endsWith(".x.com")||host.indexOf("twitter.com")>=0)key="x";else if(host.indexOf("tiktok.com")>=0)key="tiktok";else if(host.indexOf("instagram.com")>=0)key="instagram";else if(host.indexOf("dailymotion.com")>=0||host==="dai.ly")key="dailymotion";else if(host.indexOf("twitch.tv")>=0)key="twitch";else if(/\.(mp4|webm|ogv|ogg)(?:$)/i.test(value))key="direct";if(key==="empty"){pLogo.textContent="▶";pLogo.style.background="#50575e";pName.textContent="'.esc_js(__('Paste a video URL to see platform instructions','kashida-video-embed')).'";pSteps.textContent="'.esc_js(__('Choose a platform URL. Instructions for copying its link or embed code will appear here.','kashida-video-embed')).'";pDoc.hidden=true;return;}var item=providerData[key]||providerData.unknown;pLogo.textContent=item.mark;pLogo.style.background=item.color;pName.textContent=item.name;pSteps.textContent=item.steps;pDoc.href=item.url;pDoc.hidden=false;}var dmField=document.getElementById("kve-dailymotion-player-field");function toggleDmField(){if(!dmField||!urlInput)return;var v=urlInput.value.toLowerCase();dmField.style.display=(v.indexOf("dailymotion.com")!==-1||v.indexOf("dai.ly/")!==-1)?"":"none";}if(urlInput){urlInput.addEventListener("input",function(){updateProvider();toggleDmField();});urlInput.addEventListener("change",function(){updateProvider();toggleDmField();});updateProvider();toggleDmField();}var codeInput=document.getElementById("kve-embed-code");if(codeInput){codeInput.addEventListener("input",function(){if(mode.value==="code"){var m=codeInput.value.match(/src=[\"\']([^\"\']+)/i);if(m){updateProvider(m[1]);}}});if(mode.value==="code"){var initialEmbed=codeInput.value.match(/src=[\"\']([^\"\']+)/i);if(initialEmbed)updateProvider(initialEmbed[1]);}}var b=document.getElementById("kve-preview");if(b)b.addEventListener("click",function(){var u=document.getElementById("kve-url").value,a=document.getElementById("kve-preview-area");if(mode.value==="url"&&!u){a.textContent="'.esc_js(__('Enter a video URL first.','kashida-video-embed')).'";return;}b.disabled=true;var d=new FormData();d.append("action","kve_preview_video");d.append("nonce","'.esc_js(wp_create_nonce('kve_preview_video')).'");d.append("url",u);d.append("ratio",document.getElementById("kve-ratio").value);d.append("display_mode",document.getElementById("kve-display-mode").value);d.append("input_mode",mode.value);d.append("embed_code",document.getElementById("kve-embed-code").value);d.append("dailymotion_player_id",document.getElementById("kve-dailymotion-player-id").value);d.append("controls",document.querySelector("[name=controls]").checked?"1":"0");d.append("lazy",document.querySelector("[name=lazy]").checked?"1":"0");fetch(ajaxurl,{method:"POST",credentials:"same-origin",body:d}).then(function(r){return r.json();}).then(function(r){a.innerHTML=r.success?r.data.html:"<p>Preview unavailable for this source.</p>";}).catch(function(){a.textContent="Preview unavailable.";}).finally(function(){b.disabled=false;});});var copy=document.getElementById("kve-copy-shortcode");if(copy)copy.addEventListener("click",function(){var input=document.getElementById("kve-shortcode");input.select();document.execCommand("copy");copy.textContent="'.esc_js(__('Copied','kashida-video-embed')).'";});});</script>';
        $this->footer();
    }
    public function provider_scripts() {
        wp_register_script('kve-twitter-widgets', 'https://platform.twitter.com/widgets.js', array(), self::VERSION, true);
    }
    public function ajax_preview_video() {
        if (!current_user_can('edit_posts')) wp_send_json_error();
        check_ajax_referer('kve_preview_video','nonce');
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- URLs require URL-specific sanitization; esc_url_raw() is applied before use.
        $url = isset($_POST['url']) ? esc_url_raw(trim(wp_unslash($_POST['url']))) : '';
        $ratio = isset($_POST['ratio']) ? sanitize_text_field(wp_unslash($_POST['ratio'])) : '16:9';
        $display_mode = isset($_POST['display_mode']) && sanitize_key(wp_unslash($_POST['display_mode']))==='provider' ? 'provider' : 'player';
        $input_mode = isset($_POST['input_mode']) && sanitize_key(wp_unslash($_POST['input_mode']))==='code' ? 'code' : 'url';
        $allowed_embed_html = array('iframe' => array('src'=>true,'width'=>true,'height'=>true,'title'=>true,'allow'=>true,'allowfullscreen'=>true,'loading'=>true,'frameborder'=>true,'referrerpolicy'=>true,'style'=>true));
        $embed_code = isset($_POST['embed_code']) ? wp_kses(wp_unslash($_POST['embed_code']), $allowed_embed_html) : '';
        $controls = isset($_POST['controls']) && sanitize_text_field(wp_unslash($_POST['controls']))==='1'; $lazy = !isset($_POST['lazy']) || sanitize_text_field(wp_unslash($_POST['lazy']))==='1';
        if ($input_mode==='url' && (!$url || !in_array(wp_parse_url($url,PHP_URL_SCHEME),array('http','https'),true))) wp_send_json_error();
        wp_send_json_success(array('html'=>$this->render_video($url,$ratio,$controls,$lazy,$input_mode,$display_mode,$embed_code,isset($_POST['dailymotion_player_id'])?sanitize_text_field(wp_unslash($_POST['dailymotion_player_id'])):'')));
    }
    public function save_video() {
        if (!current_user_can('edit_posts')) wp_die(esc_html__('You are not allowed to do this.','kashida-video-embed'));
        check_admin_referer('kve_save_video');
        $id = isset($_POST['video_id']) ? absint($_POST['video_id']) : 0;
        if ($id && !current_user_can('edit_post',$id)) wp_die(esc_html__('You are not allowed to edit this video.','kashida-video-embed'));
        $input_mode = isset($_POST['input_mode']) && sanitize_key(wp_unslash($_POST['input_mode']))==='code' ? 'code' : 'url';
        $allowed_embed_html = array('iframe'=>array('src'=>true,'width'=>true,'height'=>true,'title'=>true,'allow'=>true,'allowfullscreen'=>true,'loading'=>true,'frameborder'=>true,'referrerpolicy'=>true,'style'=>true));
        $embed_code = isset($_POST['embed_code']) ? wp_kses(wp_unslash($_POST['embed_code']), $allowed_embed_html) : '';
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- URLs require URL-specific sanitization; esc_url_raw() is applied before use.
        $url = isset($_POST['url']) ? esc_url_raw(trim(wp_unslash($_POST['url']))) : '';
        if ($input_mode === 'url' && (!$url || !in_array(wp_parse_url($url,PHP_URL_SCHEME),array('http','https'),true))) wp_die(esc_html__('Please enter a valid HTTP or HTTPS video URL.','kashida-video-embed'));
        if ($input_mode === 'code' && !preg_match('/<iframe\b[^>]*src=["\']https?:\/\/[^"\']+/i',$embed_code)) wp_die(esc_html__('Please provide a valid iframe embed code from a supported provider.','kashida-video-embed'));
        $display_mode = isset($_POST['display_mode']) && sanitize_key(wp_unslash($_POST['display_mode']))==='provider' ? 'provider' : 'player';
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $post = array('post_type'=>self::CPT,'post_title'=>$title ?: __('Untitled video','kashida-video-embed'),'post_status'=>'publish'); if ($id) $post['ID']=$id;
        $saved = wp_insert_post($post,true); if (is_wp_error($saved)) wp_die(esc_html($saved->get_error_message()));
        update_post_meta($saved,'_kve_url',$url); update_post_meta($saved,'_kve_input_mode',$input_mode); update_post_meta($saved,'_kve_embed_code',$input_mode==='code' ? wp_kses($embed_code,array('iframe'=>array('src'=>true,'width'=>true,'height'=>true,'title'=>true,'allow'=>true,'allowfullscreen'=>true,'loading'=>true,'frameborder'=>true,'referrerpolicy'=>true,'style'=>true))) : ''); update_post_meta($saved,'_kve_dailymotion_player_id',isset($_POST['dailymotion_player_id'])?sanitize_text_field(wp_unslash($_POST['dailymotion_player_id'])):''); update_post_meta($saved,'_kve_display_mode',$display_mode); $ratio=isset($_POST['ratio'])?sanitize_text_field(wp_unslash($_POST['ratio'])):'16:9'; if(!in_array($ratio,array('16:9','9:16','4:3','1:1'),true))$ratio='16:9'; update_post_meta($saved,'_kve_ratio',$ratio);
        update_post_meta($saved,'_kve_controls',!empty($_POST['controls'])?'1':'0');
        update_post_meta($saved,'_kve_lazy',!empty($_POST['lazy'])?'1':'0');
        wp_safe_redirect(add_query_arg(array('page'=>'kve-add-new','edit'=>$saved,'saved'=>1),admin_url('admin.php'))); exit;
    }
    public function delete_video() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read only to construct the item-specific nonce action; check_admin_referer() validates the request below.
        $id = isset($_GET['video_id']) ? absint($_GET['video_id']) : 0;
        if (!$id) wp_die(esc_html__('Invalid video ID.','kashida-video-embed'));
        check_admin_referer('kve_delete_video_'.$id);
        $item = get_post($id);
        if (!$item || $item->post_type !== self::CPT) wp_die(esc_html__('Video not found.','kashida-video-embed'));
        if (!current_user_can('delete_post', $id)) wp_die(esc_html__('You are not allowed to delete this video.','kashida-video-embed'));
        $deleted = wp_delete_post($id, true);
        if (!$deleted) wp_die(esc_html__('The video could not be deleted.','kashida-video-embed'));
        wp_safe_redirect(add_query_arg(array('page'=>'kve-videos','deleted'=>1), admin_url('admin.php')));
        exit;
    }
    public function help_page() {
        if (!current_user_can('edit_posts')) return;
        $this->header(__('Complete help guide, features and troubleshooting.','kashida-video-embed'));
        echo '<div class="kve-help-guide"><div class="kve-help-column kve-help-column-primary">';
        echo '<div class="kve-card"><h2>'.esc_html__('Getting started','kashida-video-embed').'</h2><ol><li>'.esc_html__('Open Kashida Video Embed → Add New.','kashida-video-embed').'</li><li>'.esc_html__('Enter a clear title, then paste the provider’s normal share URL in Video URL. Use Embed code only if a provider’s URL cannot be embedded automatically.','kashida-video-embed').'</li><li>'.esc_html__('Choose the aspect ratio, display mode, player controls and lazy loading options.','kashida-video-embed').'</li><li>'.esc_html__('Click Refresh preview to test the current settings, then Save Video. Saving keeps you on the edit screen.','kashida-video-embed').'</li><li>'.esc_html__('Open All Videos and copy the shortcode, or insert the Kashida Video Embed block in the block editor.','kashida-video-embed').'</li></ol></div>';
        echo '<div class="kve-card"><h2>'.esc_html__('Features explained','kashida-video-embed').'</h2><ul><li><strong>'.esc_html__('Video library','kashida-video-embed').':</strong> '.esc_html__('Manage saved video entries, search by title or URL, sort the list, copy shortcodes, edit entries and delete entries.','kashida-video-embed').'</li><li><strong>'.esc_html__('URL mode','kashida-video-embed').':</strong> '.esc_html__('Paste a regular video share link. The plugin detects supported providers and creates the appropriate embed URL where supported.','kashida-video-embed').'</li><li><strong>'.esc_html__('Embed code mode','kashida-video-embed').':</strong> '.esc_html__('Paste an iframe embed code supplied by the provider. Only supported provider domains are accepted for security.','kashida-video-embed').'</li><li><strong>'.esc_html__('Display mode','kashida-video-embed').':</strong> '.esc_html__('Player-only mode minimizes surrounding provider details where the provider supports it; provider mode may show additional platform information. The provider controls the final appearance.','kashida-video-embed').'</li><li><strong>'.esc_html__('Aspect ratio','kashida-video-embed').':</strong> '.esc_html__('Choose 16:9, 9:16, 4:3 or 1:1. Some provider players may enforce their own dimensions.','kashida-video-embed').'</li><li><strong>'.esc_html__('Player controls','kashida-video-embed').':</strong> '.esc_html__('Applies to supported direct video files and providers; some platforms ignore this option.','kashida-video-embed').'</li><li><strong>'.esc_html__('Lazy loading','kashida-video-embed').':</strong> '.esc_html__('Defers iframe loading where supported and uses preload=none for direct video files to reduce initial page work.','kashida-video-embed').'</li><li><strong>'.esc_html__('Gutenberg block','kashida-video-embed').':</strong> '.esc_html__('Insert the block and provide a video URL and display options without typing a shortcode.','kashida-video-embed').'</li><li><strong>'.esc_html__('Live collection designer','kashida-video-embed').':</strong> '.esc_html__('Choose one of five layouts and adjust the layout-specific controls. The preview is a design preview; actual playback and provider metadata depend on the source platform.','kashida-video-embed').'</li></ul></div>';
        echo '<div class="kve-card"><h2>'.esc_html__('Supported providers and limitations','kashida-video-embed').'</h2><p><strong>YouTube, Vimeo, Facebook, X/Twitter, Dailymotion, TikTok, Twitch, Instagram, WordPress.tv</strong> '.esc_html__('and direct MP4, WebM, OGV/OGG video files are supported to the extent allowed by each provider.','kashida-video-embed').'</p><ul><li><strong>Twitch:</strong> '.esc_html__('Use the original stream, video or clip URL. Twitch requires HTTPS and the correct parent domain. Local development domains, nested frames, privacy settings or provider restrictions may block playback.','kashida-video-embed').'</li><li><strong>Dailymotion:</strong> '.esc_html__('Paste the original share URL. If the provider rejects the player or the video owner restricts embedding, use a valid Player ID or the official embed code supplied by Dailymotion Studio.','kashida-video-embed').'</li><li><strong>'.esc_html__('Private/restricted videos','kashida-video-embed').':</strong> '.esc_html__('Videos requiring login, restricted by their owner, removed, age-gated or blocked from third-party embedding may not play. The plugin cannot bypass provider restrictions.','kashida-video-embed').'</li><li><strong>'.esc_html__('Direct files','kashida-video-embed').':</strong> '.esc_html__('Use a public HTTPS file URL ending in .mp4, .webm, .ogv or .ogg. The server must return a supported video MIME type and allow access to the file.','kashida-video-embed').'</li></ul></div>';
        echo '</div><div class="kve-help-column kve-help-column-secondary">';
        echo '<div class="kve-card"><h2>'.esc_html__('Shortcode and block usage','kashida-video-embed').'</h2><p>'.esc_html__('Saved video entry: copy the shortcode shown in All Videos. Example:','kashida-video-embed').'</p><p><code>[kashida_video_embed id="123"]</code></p><p>'.esc_html__('Use a URL directly without saving it first:','kashida-video-embed').'</p><p><code>[kashida_video_embed url="https://youtu.be/VIDEO_ID" ratio="16:9"]</code></p><p>'.esc_html__('Available shortcode attributes include id, url, ratio (16:9, 9:16, 4:3, 1:1), controls (yes/no), lazy (yes/no) and display (player/provider). When id is used, saved settings for that video take priority.','kashida-video-embed').'</p></div>';
        echo '<div class="kve-card"><h2>'.esc_html__('Video collections and layouts','kashida-video-embed').'</h2><p>'.esc_html__('Open Video Collections to create or edit a reusable group. Select saved videos, choose a layout, configure its options, check the live preview, save, then copy the collection shortcode. Editing a saved collection keeps its shortcode unchanged.','kashida-video-embed').'</p><ul><li><strong>Grid:</strong> '.esc_html__('Responsive columns, title visibility, card background, title color and size, spacing and border radius.','kashida-video-embed').'</li><li><strong>List:</strong> '.esc_html__('Vertical rows with optional information and metadata, thumbnail width, row background, title color and size, spacing and border radius.','kashida-video-embed').'</li><li><strong>Carousel:</strong> '.esc_html__('Horizontal cards with responsive item counts, height, arrows, pagination dots, title and metadata visibility, overlay color and opacity, spacing and border radius.','kashida-video-embed').'</li><li><strong>Slider:</strong> '.esc_html__('One featured slide with height, image fit, autoplay interval, arrows, dots, title visibility and color, overlay color and opacity, and container/card appearance controls.','kashida-video-embed').'</li><li><strong>Featured + Playlist:</strong> '.esc_html__('A main video with a side playlist, playlist height and scrolling, active-item color, border and icon colors, metadata visibility, spacing and card appearance.','kashida-video-embed').'</li></ul><p>'.esc_html__('Settings available depend on the selected layout. Use the live preview to check visual changes, then save and test the shortcode on a front-end page. External players may look different from the preview.','kashida-video-embed').'</p><p>'.esc_html__('Example:','kashida-video-embed').' <code>[kashida_video_gallery ids="123,124,125" layout="grid" columns="3" gap="16" show_title="yes"]</code></p></div>';
        echo '<div class="kve-card kve-platform-help"><h2>'.esc_html__('How to copy a link from each platform','kashida-video-embed').'</h2><p>'.esc_html__('Copy the original public share link from the platform, then paste it into Video URL. Use Embed code only when automatic embedding is unavailable.','kashida-video-embed').'</p><details><summary>YouTube</summary><ol><li>'.esc_html__('Open the video.','kashida-video-embed').'</li><li>'.esc_html__('Select Share, then Copy link. For embed code, select Share → Embed → Copy.','kashida-video-embed').'</li><li>'.esc_html__('Paste the link into Video URL.','kashida-video-embed').'</li></ol></details><details><summary>Vimeo</summary><ol><li>'.esc_html__('Open the video and select Share.','kashida-video-embed').'</li><li>'.esc_html__('Copy the video link. If embedding is restricted, ask the owner to allow embedding on your domain.','kashida-video-embed').'</li></ol></details><details><summary>Facebook</summary><ol><li>'.esc_html__('Open the public video or Reel.','kashida-video-embed').'</li><li>'.esc_html__('Select Share or the three-dot menu, then Copy link.','kashida-video-embed').'</li><li>'.esc_html__('Private posts and videos requiring login may not embed.','kashida-video-embed').'</li></ol></details><details><summary>X / Twitter</summary><ol><li>'.esc_html__('Open the post containing the video.','kashida-video-embed').'</li><li>'.esc_html__('Select Share → Copy link.','kashida-video-embed').'</li><li>'.esc_html__('The embed may display the original post and its details, not only the video.','kashida-video-embed').'</li></ol></details><details><summary>TikTok</summary><ol><li>'.esc_html__('Open the video and select Share.','kashida-video-embed').'</li><li>'.esc_html__('Select Copy link. Alternatively, select Embed and copy the provided embed code.','kashida-video-embed').'</li></ol></details><details><summary>Instagram</summary><ol><li>'.esc_html__('Open the public post or Reel.','kashida-video-embed').'</li><li>'.esc_html__('Open the post menu and select Copy link.','kashida-video-embed').'</li><li>'.esc_html__('Private-account content cannot be embedded for public visitors.','kashida-video-embed').'</li></ol></details><details><summary>Dailymotion</summary><ol><li>'.esc_html__('Open the video and select Share → Copy link.','kashida-video-embed').'</li><li>'.esc_html__('Paste the original link. If Dailymotion rejects the player, use the official embed code or a valid Player ID from Dailymotion Studio.','kashida-video-embed').'</li></ol></details><details><summary>Twitch</summary><ol><li>'.esc_html__('Open the stream, past broadcast or clip.','kashida-video-embed').'</li><li>'.esc_html__('Select Share and copy the original URL.','kashida-video-embed').'</li><li>'.esc_html__('Test on the real HTTPS domain. Local development domains and nested frames can block the player.','kashida-video-embed').'</li></ol></details><details><summary>WordPress Media / direct video files</summary><ol><li>'.esc_html__('Open Media → Library and select the video file.','kashida-video-embed').'</li><li>'.esc_html__('Copy the File URL and paste it into Video URL.','kashida-video-embed').'</li><li>'.esc_html__('Supported formats include MP4, WebM, OGV and OGG.','kashida-video-embed').'</li></ol></details><details><summary>WordPress.tv</summary><ol><li>'.esc_html__('Open the video page and copy its public URL.','kashida-video-embed').'</li><li>'.esc_html__('Paste the link and test the preview. If it is not embeddable, use the official embed code if provided.','kashida-video-embed').'</li></ol></details></div>';
        echo '<div class="kve-card"><h2>'.esc_html__('Troubleshooting','kashida-video-embed').'</h2><details><summary>'.esc_html__('The preview is blank or shows a provider error','kashida-video-embed').'</summary><p>'.esc_html__('Check that the URL is public and copied from the provider’s Share option. Try the canonical video URL or official embed code. Refresh the preview after changing fields.','kashida-video-embed').'</p></details><details><summary>'.esc_html__('Twitch says refused to connect or parent is invalid','kashida-video-embed').'</summary><p>'.esc_html__('Test on the actual HTTPS site. The Twitch parent value must match the domain displaying the player, and nested embedding contexts can require additional parent domains. Local .local domains or security policies may prevent playback.','kashida-video-embed').'</p></details><details><summary>'.esc_html__('The video plays on one site but not another','kashida-video-embed').'</summary><p>'.esc_html__('Check the public site URL, HTTPS, cache, Content Security Policy, iframe restrictions, provider privacy settings and whether the provider allows embedding on that domain.','kashida-video-embed').'</p></details><details><summary>'.esc_html__('A shortcode stopped displaying after deletion','kashida-video-embed').'</summary><p>'.esc_html__('Deleting a saved video permanently removes its plugin entry. Shortcodes using that entry ID will no longer display. Update or remove those shortcodes from your pages.','kashida-video-embed').'</p></details><details><summary>'.esc_html__('Does the plugin send video data to Kashida?','kashida-video-embed').'</summary><p>'.esc_html__('No analytics or video data is sent to Kashida by this plugin. When a visitor loads an external player, the visitor’s browser connects to that provider and is subject to its privacy policy.','kashida-video-embed').'</p></details></div>';
        echo '<div class="kve-card"><h2>'.esc_html__('About the plugin','kashida-video-embed').'</h2><p>'.esc_html__('Learn more about Kashida Video Embed, view its official WordPress plugin page, or visit the plugin page on the Kashida website.','kashida-video-embed').'</p><p class="kve-about-links"><a class="button button-primary" href="https://wordpress.org/plugins/kashida-video-embed/" target="_blank" rel="noopener noreferrer">'.esc_html__('View plugin on WordPress.org','kashida-video-embed').'</a> <a class="button" href="https://kashida.ly/wp-plugins/kashida-video-embed/" target="_blank" rel="noopener noreferrer">'.esc_html__('View plugin on Kashida website','kashida-video-embed').'</a></p></div>';
        echo '<div class="kve-card"><h2>'.esc_html__('Privacy, compatibility and support','kashida-video-embed').'</h2><p>'.esc_html__('The plugin does not add tracking or send video information to external Kashida services. Third-party embeds may contact their providers. Keep WordPress and the plugin updated, test after major theme or provider changes, and retain a backup before upgrading.','kashida-video-embed').'</p><p><a href="https://wordpress.org/documentation/article/wordpress-embeds/" target="_blank" rel="noopener noreferrer">'.esc_html__('WordPress embed documentation','kashida-video-embed').'</a> · <a href="https://dev.twitch.tv/docs/embed/" target="_blank" rel="noopener noreferrer">'.esc_html__('Twitch embed documentation','kashida-video-embed').'</a> · <a href="https://faq.dailymotion.com/hc/en-us/articles/115008959787-Embed-your-content" target="_blank" rel="noopener noreferrer">'.esc_html__('Dailymotion embed guide','kashida-video-embed').'</a></p></div>';
        echo '</div></div>';
        $this->footer();
    }
    public function gallery_shortcode($atts) {
        $atts = shortcode_atts(array(
            'ids'=>'', 'layout'=>'grid', 'columns'=>'3', 'gap'=>'16',
            'overlay'=>'#000000', 'overlay_opacity'=>'0', 'text_color'=>'#1d2327',
            'title_size'=>'16', 'show_title'=>'yes', 'surface_color'=>'#ffffff', 'show_arrows'=>'yes', 'show_dots'=>'yes', 'autoplay'=>'no', 'thumbnails'=>'yes', 'desktop_columns'=>'3', 'tablet_columns'=>'2', 'mobile_columns'=>'1', 'slider_height'=>'420', 'list_mode'=>'details', 'ratio'=>'16:9', 'controls'=>'yes', 'lazy'=>'yes',
            'radius'=>'14', 'thumb_width'=>'300', 'interval'=>'5', 'slider_fit'=>'cover', 'playlist_height'=>'0', 'scrolling'=>'yes', 'active_color'=>'#1769aa', 'playlist_border_color'=>'#d7e2f0', 'playlist_icon_color'=>'#2f6bd8', 'slider_container_color'=>'#0b1220', 'slider_title_color'=>'#ffffff', 'show_metadata'=>'legacy'
        ), (array)$atts, 'kashida_video_gallery');
        $ids = array_values(array_unique(array_filter(array_map('absint', explode(',', (string)$atts['ids'])))));
        if (!$ids) return '';
        $layout = in_array($atts['layout'], array('grid','list','carousel','slider','featured_playlist'), true) ? $atts['layout'] : 'grid';
        $columns = max(1, min(6, absint($atts['columns'])));
        $desktop_columns = max(1,min(6,absint($atts['desktop_columns'])));
        $tablet_columns = max(1,min(4,absint($atts['tablet_columns'])));
        $mobile_columns = max(1,min(2,absint($atts['mobile_columns'])));
        $slider_height = max(180,min(1000,absint($atts['slider_height'])));
        $list_mode = in_array($atts['list_mode'],array('video_only','details'),true)?$atts['list_mode']:'details';
        $gap = max(0, min(48, absint($atts['gap'])));
        $title_size = max(10, min(40, absint($atts['title_size'])));
        $ratio = in_array($atts['ratio'], array('16:9','9:16','4:3','1:1'), true) ? $atts['ratio'] : '16:9';
        $overlay = sanitize_hex_color($atts['overlay']) ?: '#000000';
        $text_color = sanitize_hex_color($atts['text_color']) ?: '#ffffff';
        $surface_color = sanitize_hex_color($atts['surface_color']) ?: '#ffffff';
        $show_arrows = in_array(strtolower((string)$atts['show_arrows']), array('yes','true','1'), true);
        $show_dots = in_array(strtolower((string)$atts['show_dots']), array('yes','true','1'), true);
        $autoplay = in_array(strtolower((string)$atts['autoplay']), array('yes','true','1'), true);
        $opacity = max(0, min(100, absint($atts['overlay_opacity']))) / 100;
        $show_title = in_array(strtolower((string)$atts['show_title']), array('yes','true','1'), true);
        $radius = max(0, min(32, absint($atts['radius'])));
        $thumb_width = max(160, min(480, absint($atts['thumb_width'])));
        $interval = max(2, min(12, absint($atts['interval'])));
        $slider_fit = ($atts['slider_fit'] === 'contain') ? 'contain' : 'cover';
        $playlist_height = absint($atts['playlist_height']); if ($playlist_height) $playlist_height = max(240, min(800, $playlist_height));
        $scrolling = in_array(strtolower((string)$atts['scrolling']), array('yes','true','1'), true);
        $active_color = sanitize_hex_color($atts['active_color']) ?: '#1769aa'; $playlist_border_color = sanitize_hex_color($atts['playlist_border_color']) ?: '#d7e2f0'; $playlist_icon_color = sanitize_hex_color($atts['playlist_icon_color']) ?: '#2f6bd8'; $slider_container_color = sanitize_hex_color($atts['slider_container_color']) ?: '#0b1220'; $slider_title_color = sanitize_hex_color($atts['slider_title_color']) ?: '#ffffff';
        $meta_raw = strtolower((string)$atts['show_metadata']);
        $show_metadata = in_array($meta_raw, array('yes','true','1'), true);
        $show_list_provider = ($meta_raw !== 'no');
        $uid = 'kve-gallery-' . wp_unique_id();
        $items = array();
        $featured_items = array();
        foreach ($ids as $id) {
            $post = get_post($id);
            if (!$post || $post->post_type !== self::CPT || $post->post_status !== 'publish') continue;
            $video = $this->shortcode(array('id'=>$id, 'ratio'=>$ratio, 'controls'=>$atts['controls'], 'lazy'=>$atts['lazy']));
            if (!$video) continue;
            $title_text = get_the_title($id);
            $title = esc_html($title_text);
            $source_url = (string)get_post_meta($id,'_kve_url',true); $source_host = strtolower((string)wp_parse_url($source_url,PHP_URL_HOST)); $provider = $source_host ? preg_replace('/^www\./','',$source_host) : __('Video source','kashida-video-embed');
            $list_details = ($layout === 'list' && $list_mode === 'details') ? '<div class="kve-list-source-details">'.($show_title?'<h3 class="kve-gallery-title">'.$title.'</h3>':'').($show_list_provider?'<span class="kve-list-provider">'.esc_html($provider).'</span>':'').'<span class="kve-list-description">'.esc_html__('Video from','kashida-video-embed').' '.esc_html($provider).'</span></div>' : '';
            $meta_html = $show_metadata ? '<div class="kve-gallery-meta">'.esc_html($provider).'</div>' : '';
            if ($layout === 'slider') { $items[] = '<article class="kve-gallery-item">'.($show_title?'<h3 class="kve-gallery-title">'.($show_metadata?'<span class="kve-slide-kicker">'.esc_html($provider).'</span>':'').$title.'</h3>':'').'<div class="kve-gallery-player">'.$video.'</div></article>'; } elseif ($layout === 'list') { $items[] = '<article class="kve-gallery-item"><div class="kve-gallery-player">'.$video.'</div>'.$list_details.'</article>'; } else { $items[] = '<article class="kve-gallery-item"><div class="kve-gallery-player">'.$video.'</div>'.($show_title?'<h3 class="kve-gallery-title">'.$title.'</h3>':'').$meta_html.'</article>'; }
            $featured_items[] = array('id'=>$id, 'title'=>$title, 'video'=>$video, 'provider'=>$provider);
        }
        if (!$items) return '';
        $class = 'kve-gallery kve-gallery-' . $layout . ($show_metadata ? ' kve-has-meta' : '');
        $style = '--kve-columns:'.$columns.';--kve-desktop-columns:'.$desktop_columns.';--kve-tablet-columns:'.$tablet_columns.';--kve-mobile-columns:'.$mobile_columns.';--kve-slider-height:'.$slider_height.'px;--kve-gap:'.$gap.'px;--kve-overlay:'.$overlay.';--kve-overlay-opacity:'.$opacity.';--kve-overlay-opacity-percent:'.($opacity*100).'%;--kve-surface:'.$surface_color.';--kve-text-color:'.$text_color.';--kve-title-size:'.$title_size.'px;--kve-radius:'.$radius.'px;--kve-thumb-width:'.$thumb_width.'px;--kve-active:'.$active_color.';--kve-playlist-border:'.$playlist_border_color.';--kve-playlist-icon:'.$playlist_icon_color.';--kve-slider-container:'.$slider_container_color.';--kve-slider-title-color:'.$slider_title_color.';--kve-fit:'.$slider_fit.';--kve-ratio:'.( $ratio === '9:16' ? '177.78%' : ($ratio === '4:3' ? '75%' : ($ratio === '1:1' ? '100%' : '56.25%')) ).';';
        $out = '<div id="'.esc_attr($uid).'" class="'.esc_attr($class).'" style="'.esc_attr($style).'" data-layout="'.esc_attr($layout).'" data-autoplay="'.($autoplay?'1':'0').'" data-interval="'.absint($interval).'" data-playlist-height="'.absint($playlist_height).'" data-scroll="'.($scrolling?'1':'0').'">';
        if ($layout === 'featured_playlist') {
            $out .= '<div class="kve-featured-layout"><div class="kve-featured-main">';
            foreach ($featured_items as $index=>$fi) {
                $out .= '<section class="kve-featured-player'.($index===0?' is-active':'').'" data-index="'.absint($index).'" '.($index===0?'':'hidden').'>'.($show_title?'<h3 class="kve-gallery-title">'.$fi['title'].'</h3>':'').'<div class="kve-gallery-player">'.$fi['video'].'</div></section>';
            }
            $out .= '</div><div class="kve-featured-playlist" role="list">';
            foreach ($featured_items as $index=>$fi) {
                $out .= '<button type="button" class="kve-featured-choice'.($index===0?' is-active':'').'" data-index="'.absint($index).'" role="listitem" aria-pressed="'.($index===0?'true':'false').'"><span class="kve-featured-choice-icon" aria-hidden="true">&#9654;</span><span class="kve-featured-choice-text"><span class="kve-featured-choice-title">'.$fi['title'].'</span>'.($show_metadata?'<span class="kve-featured-choice-meta">'.esc_html($fi['provider']).'</span>':'').'</span></button>';
            }
            $out .= '</div></div>';
        } else {
            if (($layout === 'carousel' || $layout === 'slider') && $show_arrows) $out .= '<div class="kve-gallery-nav"><button type="button" class="kve-gallery-prev" aria-label="'.esc_attr__('Previous videos','kashida-video-embed').'">&#8249;</button><button type="button" class="kve-gallery-next" aria-label="'.esc_attr__('Next videos','kashida-video-embed').'">&#8250;</button></div>';
            $out .= '<div class="kve-gallery-track">'.implode('', $items).'</div>';
            if (($layout === 'carousel' || $layout === 'slider') && $show_dots) { $out .= '<div class="kve-gallery-dots" aria-label="'.esc_attr__('Slide navigation','kashida-video-embed').'">'; foreach ($items as $di=>$unused) { /* translators: %d is the one-based position of the video in the carousel. */ $dot_label = sprintf(__('Go to video %d','kashida-video-embed'), $di + 1); $out .= '<button type="button" class="kve-gallery-dot'.($di===0?' is-active':'').'" data-index="'.absint($di).'" aria-label="'.esc_attr($dot_label).'"></button>'; } $out .= '</div>'; }
        }
        $out .= '</div>';
        return $out;
    }

    public function shortcode($atts) {
        $atts = shortcode_atts(array('id'=>0,'url'=>'','ratio'=>'16:9','controls'=>'yes','lazy'=>'yes','display'=>'player'),$atts,'kashida_video_embed');
        if ($atts['id']) { $id=absint($atts['id']); if (get_post_type($id)!==self::CPT) return ''; $atts['url']=get_post_meta($id,'_kve_url',true); $atts['input_mode']=get_post_meta($id,'_kve_input_mode',true) ?: 'url'; $atts['embed_code']=get_post_meta($id,'_kve_embed_code',true); $atts['dailymotion_player_id']=get_post_meta($id,'_kve_dailymotion_player_id',true); $atts['display']=get_post_meta($id,'_kve_display_mode',true) ?: 'player'; $atts['ratio']=get_post_meta($id,'_kve_ratio',true) ?: $atts['ratio']; $atts['controls']=get_post_meta($id,'_kve_controls',true)==='0'?'no':'yes'; $atts['lazy']=get_post_meta($id,'_kve_lazy',true)==='0'?'no':'yes'; }
        return $this->render_video($atts['url'],$atts['ratio'],$atts['controls']==='yes',$atts['lazy']==='yes',isset($atts['input_mode'])?$atts['input_mode']:'url',isset($atts['display'])?$atts['display']:'player',isset($atts['embed_code'])?$atts['embed_code']:'',isset($atts['dailymotion_player_id'])?$atts['dailymotion_player_id']:'');
    }
    private function render_video($url,$ratio='16:9',$controls=true,$lazy=true,$input_mode='url',$display_mode='player',$embed_code='',$dailymotion_player_id='') {
        if ($input_mode==='code' && $embed_code) { if (!preg_match('/<iframe\b[^>]*src=["\'](https?:\/\/[^"\']+)/i',$embed_code,$m)) return ''; $src=esc_url_raw($m[1]); $host=strtolower((string)wp_parse_url($src,PHP_URL_HOST)); $allowed=(bool)preg_match('/(^|\.)((youtube(-nocookie)?\.com)|(youtu\.be)|(vimeo\.com)|(facebook\.com)|(twitter\.com)|(x\.com)|(dailymotion\.com)|(twitch\.tv)|(tiktok\.com)|(reddit\.com)|(bsky\.app)|(wordpress\.tv))$/',$host); if (!$allowed) return '<div class="kve-unavailable">'.esc_html__('This embed code uses an unsupported provider domain.','kashida-video-embed').'</div>'; return '<div class="kve-video" style="--kve-ratio:56.25%"><iframe src="'.esc_url($src).'" title="'.esc_attr__('Embedded video','kashida-video-embed').'" loading="'.($lazy?'lazy':'eager').'" allow="autoplay; encrypted-media; picture-in-picture; web-share" allowfullscreen></iframe></div>'; }
        $url=esc_url_raw(trim((string)$url)); if (!$url || !in_array(wp_parse_url($url,PHP_URL_SCHEME),array('http','https'),true)) return '';
        $ratio=in_array($ratio,array('16:9','9:16','4:3','1:1'),true)?$ratio:'16:9'; $ratios=array('16:9'=>'56.25%','9:16'=>'177.78%','4:3'=>'75%','1:1'=>'100%');
        $path=(string)wp_parse_url($url,PHP_URL_PATH); $ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
        $direct=in_array($ext,array('mp4','webm','ogv','ogg'),true);
        if ($direct) { $mime=$ext==='mp4'?'video/mp4':($ext==='webm'?'video/webm':'video/ogg'); return '<div class="kve-video" style="--kve-ratio:'.$ratios[$ratio].'"><video '.($controls?'controls ':'').'playsinline preload="'.($lazy?'none':'metadata').'" style="width:100%;height:100%"><source src="'.esc_url($url).'" type="'.esc_attr($mime).'"></video></div>'; }
        $host=strtolower((string)wp_parse_url($url,PHP_URL_HOST)); $embed='';
        if (preg_match('/(^|\.)youtube\.com$/',$host) || $host==='youtu.be' || $host==='www.youtube-nocookie.com') {
            $id=''; if ($host==='youtu.be') $id=trim($path,'/'); elseif (preg_match('~/(?:embed|shorts|live)/([A-Za-z0-9_-]+)~',$path,$m)) $id=$m[1]; else { parse_str((string)wp_parse_url($url,PHP_URL_QUERY),$q); $id=isset($q['v'])?sanitize_text_field($q['v']):''; }
            if ($id && preg_match('/^[A-Za-z0-9_-]{6,20}$/',$id)) $embed='https://www.youtube-nocookie.com/embed/'.$id;
        } elseif (preg_match('/(^|\.)vimeo\.com$/',$host)) { if (preg_match('~/(?:video/)?([0-9]{5,})~',$path,$m)) $embed='https://player.vimeo.com/video/'.$m[1]; }
        elseif (preg_match('/(^|\.)facebook\.com$/',$host) || $host==='fb.watch' || $host==='m.facebook.com') {
            $facebook_url=$url;
            // Facebook's official video plugin accepts public video permalink/share URLs. Keep the original URL intact.
            $embed='https://www.facebook.com/plugins/video.php?href='.rawurlencode($facebook_url).'&show_text=' . ($display_mode==='provider'?'true':'false') . '&width=1280';
        }
        elseif (preg_match('/(^|\.)x\.com$/',$host) || preg_match('/(^|\.)twitter\.com$/',$host)) {
            // X's official oEmbed always includes the post UI. In Video only mode, try the
            // public syndication response for a direct MP4 variant instead of rendering the post.
            if ($display_mode !== 'provider') {
                if (preg_match('~/status/(\d+)~', $path, $tweet_match)) {
                    $tweet_id = $tweet_match[1];
                    $cache_key = 'kve_x_video_' . $tweet_id;
                    $video_url = get_transient($cache_key);
                    if (false === $video_url) {
                        $video_url = '';
                        $request = wp_remote_get('https://cdn.syndication.twimg.com/tweet-result?id=' . rawurlencode($tweet_id) . '&lang=en', array('timeout'=>8, 'redirection'=>2, 'headers'=>array('Accept'=>'application/json')));
                        if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) === 200) {
                            $data = json_decode(wp_remote_retrieve_body($request), true);
                            $variants = array();
                            if (is_array($data)) {
                                if (!empty($data['video']['variants']) && is_array($data['video']['variants'])) $variants = $data['video']['variants'];
                                elseif (!empty($data['legacy']['extended_entities']['media'][0]['video_info']['variants']) && is_array($data['legacy']['extended_entities']['media'][0]['video_info']['variants'])) $variants = $data['legacy']['extended_entities']['media'][0]['video_info']['variants'];
                                elseif (!empty($data['mediaDetails'][0]['video_info']['variants']) && is_array($data['mediaDetails'][0]['video_info']['variants'])) $variants = $data['mediaDetails'][0]['video_info']['variants'];
                            }
                            $best_bitrate = -1;
                            foreach ($variants as $variant) {
                                if (empty($variant['url']) || (isset($variant['content_type']) && $variant['content_type'] !== 'video/mp4')) continue;
                                $variant_host = strtolower((string) wp_parse_url($variant['url'], PHP_URL_HOST));
                                if (!preg_match('/(^|\.)video\.twimg\.com$/', $variant_host)) continue;
                                $bitrate = isset($variant['bitrate']) ? absint($variant['bitrate']) : 0;
                                if ($bitrate >= $best_bitrate) { $best_bitrate = $bitrate; $video_url = esc_url_raw($variant['url']); }
                            }
                        }
                        set_transient($cache_key, $video_url, $video_url ? 6 * HOUR_IN_SECONDS : 30 * MINUTE_IN_SECONDS);
                    }
                    if ($video_url) return '<div class="kve-video" style="--kve-ratio:'.$ratios[$ratio].'"><video '.($controls?'controls ':'').'playsinline preload="'.($lazy?'none':'metadata').'" style="width:100%;height:100%"><source src="'.esc_url($video_url).'" type="video/mp4"></video></div>';
                }
                return '<div class="kve-unavailable"><p>'.esc_html__('X does not provide a video-only stream for this post. Try another public video post or choose “Show provider details and interface”.','kashida-video-embed').'</p><a href="'.esc_url($url).'" target="_blank" rel="noopener noreferrer">'.esc_html__('Open video on X','kashida-video-embed').'</a></div>';
            }
            $cache_key = 'kve_x_embed_' . md5($url);
            $response = get_transient($cache_key);
            if (false === $response) {
                $request = wp_remote_get('https://publish.twitter.com/oembed?omit_script=true&dnt=true&url=' . rawurlencode($url), array('timeout'=>8, 'redirection'=>3));
                if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) === 200) {
                    $data = json_decode(wp_remote_retrieve_body($request), true);
                    $response = is_array($data) && !empty($data['html']) ? $data['html'] : '';
                    set_transient($cache_key, $response, 12 * HOUR_IN_SECONDS);
                } else { $response = ''; set_transient($cache_key, '', HOUR_IN_SECONDS); }
            }
            if ($response) {
                wp_enqueue_script('kve-twitter-widgets');
                $safe = wp_kses($response, array('blockquote'=>array('class'=>true,'cite'=>true),'a'=>array('href'=>true,'rel'=>true)));
                return '<div class="kve-social-embed">'.$safe.'</div>';
            }
        }
        // Dailymotion's current embed endpoint requires a valid Player ID.
        if (preg_match('/(^|\.)dailymotion\.com$/',$host) || $host==='dai.ly') {
            $id='';
            if (preg_match('~/(?:video/)?([a-zA-Z0-9]+)(?:_|$|/)~',$path,$m)) $id=$m[1];
            $player_id = sanitize_text_field((string)$dailymotion_player_id);
            // Use the documented sample player as a fallback so a pasted share URL
            // can be previewed without requiring a per-video setting. Sites can still
            // override this with their own Player ID in the field.
            if (!$player_id || !preg_match('/^[a-zA-Z0-9]+$/',$player_id)) {
                $player_id = 'x8lr5';
            }
            if ($id) {
                $embed='https://geo.dailymotion.com/player/'.rawurlencode($player_id).'.html?video='.rawurlencode($id);
            }
        }
        // Build Twitch parent parameters from the active WordPress installation and
        // the actual request host. This supports different domains, www/non-www,
        // subdomains, and installations where home_url() and site_url() differ.
        // Twitch requires each hostname to be passed as a separate parent parameter.
        $is_twitch = (bool)preg_match('/(^|\.)twitch\.tv$/',$host);
        if (!$embed && $is_twitch) {
            $parent_candidates = array(
                wp_parse_url(home_url(), PHP_URL_HOST),
                wp_parse_url(site_url(), PHP_URL_HOST),
                isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '',
            );
            $parent_hosts = array();
            foreach ($parent_candidates as $candidate) {
                $candidate = strtolower(trim((string) $candidate));
                // A Host header may include a port; Twitch parent values must not.
                if (strpos($candidate, ']') === false) {
                    $candidate = preg_replace('/:\d+$/', '', $candidate);
                } elseif (preg_match('/^\[([^]]+)\](?::\d+)?$/', $candidate, $ip_match)) {
                    $candidate = $ip_match[1];
                }
                $candidate = rtrim($candidate, '.');
                if ($candidate !== '' && preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?))*$/', $candidate)) {
                    $parent_hosts[] = $candidate;
                }
            }
            // If WordPress is normally available on both www and apex, include both.
            foreach (array_values(array_unique($parent_hosts)) as $candidate) {
                if (strpos($candidate, 'www.') === 0) {
                    $parent_hosts[] = substr($candidate, 4);
                } elseif (substr_count($candidate, '.') >= 1 && filter_var($candidate, FILTER_VALIDATE_IP) === false) {
                    $parent_hosts[] = 'www.' . $candidate;
                }
            }
            $parent_query = '';
            foreach (array_unique($parent_hosts) as $parent_host) {
                $parent_query .= '&parent=' . rawurlencode($parent_host);
            }
            $query = array();
            parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);

            if ($host === 'player.twitch.tv') {
                // The plugin previously rejected player.twitch.tv URLs because their ID is in the query string.
                if (!empty($query['video'])) {
                    $video_id = sanitize_text_field((string) $query['video']);
                    if (preg_match('/^v?\d+$/', $video_id)) {
                        if (strpos($video_id, 'v') !== 0) $video_id = 'v' . $video_id;
                        $embed = 'https://player.twitch.tv/?video=' . rawurlencode($video_id) . $parent_query . '&autoplay=false';
                    }
                } elseif (!empty($query['channel']) && preg_match('/^[a-zA-Z0-9_]+$/', (string) $query['channel'])) {
                    $embed = 'https://player.twitch.tv/?channel=' . rawurlencode((string) $query['channel']) . $parent_query . '&autoplay=false';
                } elseif (!empty($query['collection']) && preg_match('/^[a-zA-Z0-9]+$/', (string) $query['collection'])) {
                    $embed = 'https://player.twitch.tv/?collection=' . rawurlencode((string) $query['collection']) . $parent_query . '&autoplay=false';
                }
            } elseif ($host === 'clips.twitch.tv' && preg_match('~^/([a-zA-Z0-9_-]+)~', $path, $m)) {
                $embed = 'https://clips.twitch.tv/embed?clip=' . rawurlencode($m[1]) . $parent_query . '&autoplay=false';
            } elseif (preg_match('~^/videos/(\d+)~', $path, $m)) {
                $embed = 'https://player.twitch.tv/?video=v' . rawurlencode($m[1]) . $parent_query . '&autoplay=false';
            } elseif (preg_match('~^/([a-zA-Z0-9_]+)/clip/([a-zA-Z0-9_-]+)~', $path, $m)) {
                $embed = 'https://clips.twitch.tv/embed?clip=' . rawurlencode($m[2]) . $parent_query . '&autoplay=false';
            } elseif (preg_match('~^/([a-zA-Z0-9_-]+)$~', $path, $m) && $display_mode === 'provider') {
                $embed = 'https://player.twitch.tv/?channel=' . rawurlencode($m[1]) . $parent_query . '&autoplay=false';
            }
        }
        // TikTok documents the /player/v1/{post_id} player endpoint for direct iframe embeds.
        if (!$embed && preg_match('/(^|\.)tiktok\.com$/',$host) && preg_match('~^/@[^/]+/video/(\d+)~',$path,$m)) {
            $embed='https://www.tiktok.com/player/v1/'.rawurlencode($m[1]).'?controls='.($controls?'1':'0').'&description='.($display_mode==='provider'?'1':'0');
        }
        // Keep Instagram's original content type; public posts/Reels must allow embedding.
        if (!$embed && preg_match('/(^|\.)instagram\.com$/',$host) && preg_match('~^/(reel|reels|p|tv)/([A-Za-z0-9_-]+)~',$path,$m)) {
            $kind = $m[1] === 'reels' ? 'reel' : $m[1];
            $embed='https://www.instagram.com/'.$kind.'/'.rawurlencode($m[2]).'/embed/';
        }
        if ($embed) {
            $is_twitch_embed = (strpos($embed, 'player.twitch.tv') !== false || strpos($embed, 'clips.twitch.tv') !== false);
            $embed_ratio = $is_twitch_embed ? '75%' : $ratios[$ratio];
            $provider_details = ($display_mode === 'provider' ? '<div class="kve-provider-details"><span>' . esc_html($host) . '</span></div>' : '');
            if ($is_twitch_embed) {
                // Use a complete iframe URL generated server-side. Inline scripts inside
                // AJAX previews inserted with innerHTML do not reliably execute, leaving
                // a blank frame or a Twitch frame without the required parent parameter.
                // The URL builder above includes the WordPress home/site/request hostnames.
                return $provider_details . '<div class="kve-video kve-twitch-video" style="--kve-ratio:' . $embed_ratio . ';min-width:min(100%,400px);min-height:300px"><iframe src="' . esc_url($embed) . '" title="' . esc_attr__('Embedded video','kashida-video-embed') . '" loading="' . ($lazy?'lazy':'eager') . '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></div>';
            }
            return $provider_details . '<div class="kve-video" style="--kve-ratio:' . $embed_ratio . ';min-width:min(100%,400px)"><iframe src="' . esc_url($embed) . '" title="' . esc_attr__('Embedded video','kashida-video-embed') . '" loading="' . ($lazy?'lazy':'eager') . '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></div>';
        }
        // Use WordPress oEmbed for providers it explicitly supports; never print arbitrary provider HTML.
        $html=wp_oembed_get($url,array('width'=>1280));
        if ($html && preg_match('/<iframe\b/i',$html)) return ($display_mode==='provider' ? '<div class="kve-provider-details"><span>'.esc_html($host).'</span></div>' : '') . '<div class="kve-video" style="--kve-ratio:'.$ratios[$ratio].'">'.wp_kses($html,array('iframe'=>array('src'=>true,'width'=>true,'height'=>true,'title'=>true,'allow'=>true,'allowfullscreen'=>true,'loading'=>true,'frameborder'=>true,'referrerpolicy'=>true,'sandbox'=>true,'class'=>true,'style'=>true))).'</div>';
        return '<div class="kve-unavailable"><p>'.esc_html__('This video provider does not allow automatic embedding for this URL. Try a supported share link or check the provider’s embedding restrictions.','kashida-video-embed').'</p><a href="'.esc_url($url).'" target="_blank" rel="noopener noreferrer">'.esc_html__('Open video on its website','kashida-video-embed').'</a></div>';
    }
}
new Kashida_Video_Embed();
