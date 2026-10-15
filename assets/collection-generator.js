(function(){'use strict';
function init(){
var box=document.querySelector('.kve-gallery-generator');
if(!box||box.dataset.kveInit==='1')return;box.dataset.kveInit='1';
var $=function(id){return document.getElementById(id)};
var out=$('kve-gallery-output'),copy=$('kve-gallery-copy'),status=$('kve-generator-status'),save=$('kve-save-collection'),dataField=$('kve-save-data'),nameField=$('kve-save-name'),nameInput=$('kve-collection-name'),form=$('kve-save-collection-form');
if(!out||!copy||!status||!save||!dataField)return;
var generated=false,initial=window.kveEditingCollection||null,designer=box.querySelector('.kve-designer');

var IS_AR=(document.documentElement.lang||'').toLowerCase().indexOf('ar')===0;
var LAYOUT_NAMES=IS_AR?{grid:'تخطيط الشبكة',list:'تخطيط القائمة',carousel:'تخطيط العرض الدائري',slider:'تخطيط الشرائح',featured_playlist:'فيديو رئيسي وقائمة تشغيل'}:{grid:'Grid layout',list:'List layout',carousel:'Carousel layout',slider:'Slider layout',featured_playlist:'Featured + Playlist layout'};
var SAMPLE=[['The Future of Independent Podcasting','youtube.com','24:18','#3b2a1f,#c98a4b'],['Hidden Coastlines of the Atlantic','vimeo.com','12:47','#0e5a73,#f2a65a'],['Building a Design System in TypeScript','youtube.com','38:02','#0b1d44,#2f6bd8'],['Plating Like a Pro: Seasonal Tasting Menu','facebook.com','18:55','#2a3a2a,#e0c48a'],['Studio Sessions: Interview Lighting Basics','tiktok.com','09:31','#3b2a1f,#8a5a3a'],['Coastal Trails: A Hiker\u2019s Field Guide','instagram.com','15:20','#0e5a73,#e98d4b'],['From Figma to Production CSS','twitch.tv','1:04:12','#0b1d44,#1f4fae'],['Kitchen Science: The Perfect Sear','x.com','11:08','#2a3a2a,#c9a45c']];

var FIELDS=[
['layout','kve-gallery-layout','v'],['columns','kve-gallery-columns','v'],['desktop_columns','kve-gallery-desktop-columns','v'],['tablet_columns','kve-gallery-tablet-columns','v'],['mobile_columns','kve-gallery-mobile-columns','v'],
['slider_height','kve-gallery-slider-height','v'],['slider_fit','kve-gallery-slider-fit','v'],['slider_container_color','kve-gallery-slider-container','v'],['slider_title_color','kve-gallery-slider-title-color','v'],['list_mode','kve-gallery-list-mode','v'],['thumb_width','kve-gallery-thumb-width','v'],
['gap','kve-gallery-gap','v'],['radius','kve-gallery-radius','v'],['playlist_border_color','kve-gallery-playlist-border','v'],['playlist_icon_color','kve-gallery-playlist-icon','v'],['overlay','kve-gallery-overlay','v'],['overlay_opacity','kve-gallery-opacity','v'],['text_color','kve-gallery-text','v'],['title_size','kve-gallery-title-size','v'],['surface_color','kve-gallery-surface','v'],['active_color','kve-gallery-active','v'],['interval','kve-gallery-interval','v'],['playlist_height','kve-gallery-playlist-height','v'],
['show_title','kve-gallery-show-title','b'],['show_metadata','kve-gallery-metadata','b'],['show_arrows','kve-gallery-arrows','b'],['show_dots','kve-gallery-dots','b'],['autoplay','kve-gallery-autoplay','b'],['scrolling','kve-gallery-scrolling','b'],['thumbnails','kve-gallery-thumbnails','b']
];
var DEFAULTS={desktop_columns:4,tablet_columns:2,mobile_columns:1,slider_height:440,slider_fit:'cover',list_mode:'details',thumb_width:300,gap:20,radius:12,overlay:'#0f1530',overlay_opacity:45,text_color:'#1b2340',title_size:16,surface_color:'#ffffff',active_color:'#2f6bd8',playlist_border_color:'#d7e2f0',playlist_icon_color:'#2f6bd8',slider_container_color:'#0b1220',slider_title_color:'#ffffff',interval:4,playlist_height:420,show_title:true,show_metadata:true,show_arrows:true,show_dots:true,autoplay:false,scrolling:true};

function val(id){var e=$(id);return e?e.value:''}
function ids(){return Array.prototype.slice.call(box.querySelectorAll('.kve-gallery-pick:checked')).map(function(x){return parseInt(x.value,10)}).filter(Boolean)}
function settings(){var d={ids:ids()};FIELDS.forEach(function(f){var e=$(f[1]);if(!e)return;d[f[0]]=f[2]==='b'?!!e.checked:e.value});return d}
function esc(t){var d=document.createElement('div');d.textContent=t;return d.innerHTML}
function layout(){return val('kve-gallery-layout')||'grid'}

function syncDerived(){
var dc=$('kve-gallery-desktop-columns'),c=$('kve-gallery-columns');if(dc&&c)c.value=dc.value;
var info=$('kve-gallery-info'),lm=$('kve-gallery-list-mode');if(info&&lm)lm.value=info.checked?'details':'video_only';
}
function outputs(){
box.querySelectorAll('.kve-d-range').forEach(function(r){var i=r.querySelector('input[type=range]'),o=r.querySelector('output');if(i&&o)o.textContent=i.value+(o.dataset.unit||'')});
box.querySelectorAll('.kve-d-hex').forEach(function(h){var c=$(h.dataset['for']);if(c&&document.activeElement!==h)h.value=c.value.toUpperCase()});
}
function panel(){
var l=layout();designer.dataset.layout=l;
designer.querySelectorAll('.kve-d-tab').forEach(function(t){var on=t.dataset.layout===l;t.classList.toggle('is-active',on);t.setAttribute('aria-pressed',on?'true':'false')});
designer.querySelectorAll('[data-layouts]').forEach(function(e){if(e.classList.contains('kve-d-group'))return;var show=(' '+e.dataset.layouts+' ').indexOf(' '+l+' ')>-1;e.style.display=show?'':'none'});
designer.querySelectorAll('.kve-d-group').forEach(function(g){var inLayout=(' '+g.dataset.layouts+' ').indexOf(' '+l+' ')>-1;var any=false;g.querySelectorAll('.kve-d-row').forEach(function(r){var rl=r.dataset.layouts;var ok=!rl||(' '+rl+' ').indexOf(' '+l+' ')>-1;if(ok)any=true});g.style.display=(inLayout&&any)?'':'none';var h=g.querySelector('h5');if(h){if(!h.dataset.base)h.dataset.base=h.textContent;var t=h.dataset.base;(g.dataset.altTitles||'').split('|').forEach(function(p){var kv=p.split('=');if(kv[0]===l&&kv[1])t=kv[1]});h.textContent=t}});
designer.querySelectorAll('.kve-d-color label').forEach(function(lb){if(lb.dataset.alt)lb.textContent=(l==='list')?lb.dataset.alt:lb.dataset.base});
var st=$('kve-d-stage-title');if(st)st.textContent=LAYOUT_NAMES[l]||l;
var code=$('kve-d-code');if(code)code.textContent=initial&&initial.id?'[kashida_video_collection id="'+initial.id+'"]':'[kashida_video_gallery layout="'+l+'"]';
}

function items(){
var picks=Array.prototype.slice.call(box.querySelectorAll('.kve-gallery-pick:checked')),res=[];
if(picks.length){picks.forEach(function(p,i){var lab=p.closest('label'),t=lab?lab.textContent.replace(/#\d+\s*$/,'').trim():'Video';var s=SAMPLE[i%SAMPLE.length];res.push({title:t,provider:s[1],dur:s[2],grad:s[3],desc:'Video from '+s[1]})});}
else{SAMPLE.forEach(function(s){res.push({title:s[0],provider:s[1],dur:s[2],grad:s[3],desc:'Video from '+s[1]})})}
return res;
}
function mock(it){var g=it.grad.split(',');return '<div class="kve-video kve-mock" style="background:linear-gradient(135deg,'+g[0]+','+g[1]+')"><span class="kve-mock-play"></span><span class="kve-mock-dur">'+esc(it.dur)+'</span></div>'}
function renderPreview(){
var mount=$('kve-preview-mount');if(!mount)return;
var d=settings(),l=d.layout||'grid',its=items(),show_title=d.show_title,meta=d.show_metadata,html='',list=[];
var op=Math.max(0,Math.min(100,parseInt(d.overlay_opacity,10)||0));
var style='--kve-columns:'+d.desktop_columns+';--kve-desktop-columns:'+d.desktop_columns+';--kve-tablet-columns:'+d.tablet_columns+';--kve-mobile-columns:'+d.mobile_columns+';--kve-slider-height:'+d.slider_height+'px;--kve-gap:'+d.gap+'px;--kve-overlay:'+d.overlay+';--kve-overlay-opacity:'+(op/100)+';--kve-overlay-opacity-percent:'+op+'%;--kve-surface:'+d.surface_color+';--kve-text-color:'+d.text_color+';--kve-title-size:'+d.title_size+'px;--kve-radius:'+d.radius+'px;--kve-thumb-width:'+d.thumb_width+'px;--kve-active:'+d.active_color+';--kve-playlist-border:'+d.playlist_border_color+';--kve-playlist-icon:'+d.playlist_icon_color+';--kve-slider-container:'+d.slider_container_color+';--kve-slider-title-color:'+d.slider_title_color+';--kve-fit:'+d.slider_fit+';';
var cls='kve-gallery kve-gallery-'+l+(meta?' kve-has-meta':'');
if(l==='featured_playlist'){
html+='<div class="kve-featured-layout"><div class="kve-featured-main">';
its.forEach(function(it,i){html+='<section class="kve-featured-player'+(i===0?' is-active':'')+'" data-index="'+i+'" '+(i===0?'':'hidden')+'>'+(show_title?'<h3 class="kve-gallery-title">'+esc(it.title)+'</h3>':'')+'<div class="kve-gallery-player">'+mock(it)+'</div></section>'});
html+='</div><div class="kve-featured-playlist" role="list" style="height:'+d.playlist_height+'px;max-height:'+d.playlist_height+'px;overflow-y:'+(d.scrolling?'auto':'hidden')+';align-self:start">';
its.forEach(function(it,i){html+='<button type="button" class="kve-featured-choice'+(i===0?' is-active':'')+'" data-index="'+i+'"><span class="kve-featured-choice-icon" aria-hidden="true">&#9654;</span><span class="kve-featured-choice-text"><span class="kve-featured-choice-title">'+esc(it.title)+'</span>'+(meta?'<span class="kve-featured-choice-meta">'+esc(it.provider)+'</span>':'')+'</span></button>'});
html+='</div></div>';
}else{
its.forEach(function(it){
var m=meta?'<div class="kve-gallery-meta">'+esc(it.provider)+'</div>':'';
if(l==='slider')list.push('<article class="kve-gallery-item">'+(show_title?'<h3 class="kve-gallery-title">'+(meta?'<span class="kve-slide-kicker">'+esc(it.provider)+'</span>':'')+esc(it.title)+'</h3>':'')+'<div class="kve-gallery-player">'+mock(it)+'</div></article>');
else if(l==='list'){var det=d.list_mode==='details'?'<div class="kve-list-source-details">'+(show_title?'<h3 class="kve-gallery-title">'+esc(it.title)+'</h3>':'')+(meta?'<span class="kve-list-provider">'+esc(it.provider)+'</span>':'')+'<span class="kve-list-description">'+esc(it.desc)+'</span></div>':'';list.push('<article class="kve-gallery-item"><div class="kve-gallery-player">'+mock(it)+'</div>'+det+'</article>')}
else list.push('<article class="kve-gallery-item"><div class="kve-gallery-player">'+mock(it)+'</div>'+(show_title?'<h3 class="kve-gallery-title">'+esc(it.title)+'</h3>':'')+m+'</article>');
});
if((l==='carousel'||l==='slider')&&d.show_arrows)html+='<div class="kve-gallery-nav"><button type="button" class="kve-gallery-prev" aria-label="Previous">&#8249;</button><button type="button" class="kve-gallery-next" aria-label="Next">&#8250;</button></div>';
html+='<div class="kve-gallery-track">'+list.join('')+'</div>';
if((l==='carousel'||l==='slider')&&d.show_dots){html+='<div class="kve-gallery-dots">';list.forEach(function(x,i){html+='<button type="button" class="kve-gallery-dot'+(i===0?' is-active':'')+'" data-index="'+i+'"></button>'});html+='</div>'}
}
mount.innerHTML='<div class="'+cls+'" style="'+style+'" data-layout="'+l+'">'+html+'</div>';
var c=$('kve-d-count');if(c)c.textContent=(ids().length||its.length)+(ids().length?' selected':' sample')+' videos';
}

function bindPreview(){
var mount=$('kve-preview-mount');if(!mount)return;
mount.addEventListener('click',function(e){
var g=mount.querySelector('.kve-gallery'),track=g&&g.querySelector('.kve-gallery-track');
var n=e.target.closest('.kve-gallery-next,.kve-gallery-prev');
if(n&&track){var step=track.clientWidth*(g.dataset.layout==='slider'?1:.8);track.scrollBy({left:n.classList.contains('kve-gallery-next')?step:-step,behavior:'smooth'});return}
var c=e.target.closest('.kve-featured-choice');
if(c){var i=c.dataset.index;g.querySelectorAll('.kve-featured-choice').forEach(function(b){b.classList.remove('is-active')});c.classList.add('is-active');g.querySelectorAll('.kve-featured-player').forEach(function(p){var on=p.dataset.index===i;p.hidden=!on;p.classList.toggle('is-active',on)})}
});
}

function layoutOptions(){syncDerived();outputs();panel();renderPreview()}
function changed(){
layoutOptions();
if(initial&&initial.id){generated=true;dataField.value=JSON.stringify(settings());if(nameInput&&nameInput.value.trim())nameField.value=nameInput.value.trim();save.disabled=false;out.value='[kashida_video_collection id="'+initial.id+'"]';out.disabled=false;copy.disabled=false;copy.dataset.shortcode=out.value;status.textContent='Settings changed. Click Update collection to save the changes.';}
else{generated=false;save.disabled=true;out.value='';out.disabled=true;copy.disabled=true;status.textContent='Settings changed. Generate the settings again before saving.';}
}
function count(){var c=$('kve-selection-count');if(c)c.textContent=ids().length+' video(s) selected'}

function setField(key,v){var f=FIELDS.filter(function(x){return x[0]===key})[0];if(!f)return;var e=$(f[1]);if(!e)return;if(f[2]==='b')e.checked=!!v;else e.value=v}

designer.addEventListener('click',function(e){
var t=e.target.closest('.kve-d-tab');
if(t){$('kve-gallery-layout').value=t.dataset.layout;changed();return}
var b=e.target.closest('.kve-d-stepper button');
if(b){var st=b.closest('.kve-d-stepper'),inp=st.querySelector('input'),min=parseInt(st.dataset.min,10),max=parseInt(st.dataset.max,10),v=parseInt(inp.value,10)||min;v=Math.max(min,Math.min(max,v+parseInt(b.dataset.step,10)));inp.value=v;changed();return}
if(e.target.id==='kve-d-reset'){Object.keys(DEFAULTS).forEach(function(k){setField(k,DEFAULTS[k])});changed()}
});
designer.addEventListener('input',function(e){
var t=e.target;
if(t.classList.contains('kve-d-hex')){var v=t.value.trim();if(v.charAt(0)!=='#')v='#'+v;if(/^#[0-9a-fA-F]{6}$/.test(v)){var c=$(t.dataset['for']);if(c){c.value=v.toLowerCase();changed()}}return}
if(t.matches('input[type=range],input[type=color],input[type=checkbox],select'))changed();
});

box.addEventListener('change',function(e){if(e.target.matches('.kve-gallery-pick')){count();changed()}});
box.addEventListener('input',function(e){if(e.target.id==='kve-collection-name')changed()});
var all=$('kve-select-all'),clear=$('kve-clear-selection');
if(all)all.addEventListener('click',function(){box.querySelectorAll('.kve-gallery-pick').forEach(function(x){x.checked=true});count();changed()});
if(clear)clear.addEventListener('click',function(){box.querySelectorAll('.kve-gallery-pick').forEach(function(x){x.checked=false});count();changed()});

$('kve-gallery-generate').addEventListener('click',function(){var d=settings();if(!d.ids.length){status.textContent='Select at least one video first.';return}if(!nameInput.value.trim()){status.textContent='Enter a collection name first.';nameInput.focus();return}dataField.value=JSON.stringify(d);nameField.value=nameInput.value.trim();out.value='Settings ready. Save the collection to generate its permanent shortcode.';out.disabled=false;save.disabled=false;copy.disabled=true;generated=true;status.textContent='Settings generated. Click Save collection to create or update the permanent shortcode.'});
if(form)form.addEventListener('submit',function(e){if(!generated||!dataField.value||!ids().length){e.preventDefault();status.textContent='Select videos and click Generate shortcode before saving.'}});

if(initial&&initial.id){out.value='[kashida_video_collection id="'+initial.id+'"]';out.disabled=false;copy.disabled=false;copy.dataset.shortcode=out.value;copy.addEventListener('click',function(){var txt=out.value;if(!txt)return;var ta=document.createElement('textarea');ta.value=txt;document.body.appendChild(ta);ta.select();var ok=false;try{ok=document.execCommand('copy')}catch(x){}ta.remove();copy.textContent=ok?'Copied':'Select and copy';});}

var copyBtns=document.querySelectorAll('.kve-copy-shortcode');
copyBtns.forEach(function(b){b.addEventListener('click',function(e){e.preventDefault();var t=document.createElement('textarea');t.value=b.dataset.shortcode;document.body.appendChild(t);t.select();var ok=false;try{ok=document.execCommand('copy')}catch(x){}t.remove();b.textContent=ok?'Copied':'Select and copy';})});

bindPreview();count();layoutOptions();
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
