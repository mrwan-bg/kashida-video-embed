<?php
if (!defined('ABSPATH')) exit;
$kashida_video_embed_render_url = isset($attributes['url']) ? esc_url($attributes['url']) : '';
if (!$kashida_video_embed_render_url) return '';
$kashida_video_embed_render_ratio = isset($attributes['ratio']) ? sanitize_text_field($attributes['ratio']) : '16:9';
return do_shortcode('[kashida_video_embed url="' . esc_url($kashida_video_embed_render_url) . '" ratio="' . esc_attr($kashida_video_embed_render_ratio) . '" controls="' . (!empty($attributes['controls']) ? 'yes' : 'no') . '" lazy="' . (!empty($attributes['lazy']) ? 'yes' : 'no') . '"]');
