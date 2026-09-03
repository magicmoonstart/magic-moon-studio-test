<?php
/**
 * Widget renderers — corrections/renderer/widgets.php
 *
 * One function per widget type found in the reference backup (34 types).
 * Each receives the element array and the render context, returns the inner
 * HTML, and adds its own CSS to $ctx via the helpers in styles.php.
 *
 * Markup deliberately keeps Elementor's class names (.elementor-heading-title,
 * .elementor-button, .elementor-icon-list-items ...) so the site's existing
 * correction stylesheets and any "custom_css" stored in the page data keep
 * matching. None of it calls Elementor code.
 *
 * Elementor Pro widgets (absent on this site) are rendered from their stored
 * settings where that is meaningful, and otherwise render their children.
 */

if (!defined('ABSPATH')) exit;

/* ------------------------------------------------------------------ */
/* helpers                                                             */
/* ------------------------------------------------------------------ */

function mm_w_attr($v) { return esc_attr((string) $v); }

/** {url,is_external,nofollow} -> attribute string (without href when empty). */
function mm_w_link_attrs($link) {
    if (!is_array($link) || empty($link['url'])) return '';
    $a = ' href="' . esc_url($link['url']) . '"';
    $rel = array();
    if (!empty($link['is_external'])) { $a .= ' target="_blank"'; $rel[] = 'noopener'; }
    if (!empty($link['nofollow'])) $rel[] = 'nofollow';
    if ($rel) $a .= ' rel="' . implode(' ', $rel) . '"';
    if (!empty($link['custom_attributes']) && is_string($link['custom_attributes'])) {
        foreach (explode(',', $link['custom_attributes']) as $pair) {
            $kv = explode('|', $pair, 2);
            $k = preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($kv[0]));
            if ($k !== '') $a .= ' ' . $k . '="' . mm_w_attr(isset($kv[1]) ? trim($kv[1]) : '') . '"';
        }
    }
    return $a;
}

/** Elementor icon control {value, library} -> HTML. */
function mm_w_icon($icon, $extraClass = '') {
    if (!is_array($icon) || empty($icon['value'])) return '';
    if (!empty($icon['library']) && $icon['library'] === 'svg' && is_array($icon['value']) && !empty($icon['value']['url'])) {
        return '<img class="mm-icon-svg ' . mm_w_attr($extraClass) . '" src="' . esc_url($icon['value']['url']) . '" alt="" aria-hidden="true">';
    }
    if (is_string($icon['value'])) {
        return '<i class="' . mm_w_attr($icon['value'] . ' ' . $extraClass) . '" aria-hidden="true"></i>';
    }
    return '';
}

function mm_w_heading_tag($v, $default = 'h2') {
    $v = strtolower((string) $v);
    return in_array($v, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p'), true) ? $v : $default;
}

/** Image control {url,id,alt} -> <img>, using the attachment for srcset when possible. */
function mm_w_image($img, $size = 'full', $class = '', $extra = array()) {
    if (!is_array($img) || empty($img['url'])) return '';
    $attrs = array_merge(array('class' => trim('attachment-' . $size . ' size-' . $size . ' ' . $class), 'decoding' => 'async', 'loading' => 'lazy'), $extra);
    if (!empty($img['id']) && get_post_type((int) $img['id']) === 'attachment') {
        $html = wp_get_attachment_image((int) $img['id'], $size ?: 'full', false, $attrs);
        if ($html) return $html;
    }
    $alt = isset($img['alt']) ? $img['alt'] : '';
    $a = '';
    foreach ($attrs as $k => $v) $a .= ' ' . $k . '="' . mm_w_attr($v) . '"';
    return '<img src="' . esc_url($img['url']) . '" alt="' . mm_w_attr($alt) . '"' . $a . '>';
}

function mm_w_image_size($s, $key = 'image_size') {
    $v = isset($s[$key]) ? $s[$key] : 'full';
    if ($v === 'custom' && !empty($s[$key . '_custom_dimension']['width'])) {
        return array((int) $s[$key . '_custom_dimension']['width'], (int) $s[$key . '_custom_dimension']['height']);
    }
    return $v ?: 'full';
}

/* ------------------------------------------------------------------ */
/* core widgets                                                        */
/* ------------------------------------------------------------------ */

function mm_w_heading($s, &$ctx, $sel) {
    $tag = mm_w_heading_tag(isset($s['header_size']) ? $s['header_size'] : 'h2');
    $size = !empty($s['size']) ? $s['size'] : 'default';
    $title = isset($s['title']) ? $s['title'] : '';
    $inner = wp_kses_post($title);
    if (!empty($s['link']['url'])) $inner = '<a' . mm_w_link_attrs($s['link']) . '>' . $inner . '</a>';

    mm_css_align($ctx, $s, 'align', $sel);
    $c = mm_css_color($s, 'title_color'); if ($c !== '') mm_css_add($ctx, '', "$sel .elementor-heading-title", 'color:' . $c);
    mm_css_typography($ctx, $s, 'typography', "$sel .elementor-heading-title");
    if (!empty($s['text_shadow_text_shadow']) && is_array($s['text_shadow_text_shadow'])) {
        $t = $s['text_shadow_text_shadow'];
        mm_css_add($ctx, '', "$sel .elementor-heading-title", sprintf('text-shadow:%dpx %dpx %dpx %s',
            (int) (isset($t['horizontal']) ? $t['horizontal'] : 0), (int) (isset($t['vertical']) ? $t['vertical'] : 0),
            (int) (isset($t['blur']) ? $t['blur'] : 10), !empty($t['color']) ? $t['color'] : 'rgba(0,0,0,.3)'));
    }
    return "<$tag class=\"elementor-heading-title elementor-size-" . mm_w_attr($size) . "\">$inner</$tag>";
}

function mm_w_text_editor($s, &$ctx, $sel) {
    $html = isset($s['editor']) ? $s['editor'] : '';
    $html = do_shortcode($html);
    mm_css_align($ctx, $s, 'align', $sel);
    $c = mm_css_color($s, 'text_color'); if ($c !== '') mm_css_add($ctx, '', $sel, 'color:' . $c);
    mm_css_typography($ctx, $s, 'typography', $sel);
    foreach (mm_css_devices() as $d) {
        if (!empty($s['text_columns' . $d])) mm_css_add($ctx, $d, $sel, 'column-count:' . (int) $s['text_columns' . $d]);
    }
    $cls = !empty($s['drop_cap']) ? ' elementor-drop-cap' : '';
    return '<div class="elementor-text-editor' . $cls . '">' . $html . '</div>';
}

function mm_w_button($s, &$ctx, $sel) {
    $size = !empty($s['size']) ? $s['size'] : 'sm';
    $text = isset($s['text']) ? $s['text'] : '';
    $icon = mm_w_icon(isset($s['selected_icon']) ? $s['selected_icon'] : null);
    $iconAlign = !empty($s['icon_align']) ? $s['icon_align'] : 'left';
    $iconHtml = $icon ? '<span class="elementor-button-icon elementor-align-icon-' . mm_w_attr($iconAlign) . '">' . $icon . '</span>' : '';
    $hasLink = !empty($s['link']['url']);
    $tag = $hasLink ? 'a' : 'button';
    $attrs = $hasLink ? mm_w_link_attrs($s['link']) . ' role="button"' : ' type="button"';
    $btnId = !empty($s['button_css_id']) ? ' id="' . mm_w_attr($s['button_css_id']) . '"' : '';

    // alignment
    foreach (mm_css_devices() as $d) {
        if (!empty($s['align' . $d])) {
            $al = $s['align' . $d];
            if ($al === 'justify') { mm_css_add($ctx, $d, "$sel .elementor-button", 'width:100%'); }
            else mm_css_add($ctx, $d, "$sel .elementor-button-wrapper", 'text-align:' . $al);
        }
    }
    $b = "$sel .elementor-button";
    mm_css_typography($ctx, $s, 'typography', $b);
    $c = mm_css_color($s, 'button_text_color'); if ($c !== '') mm_css_add($ctx, '', $b, 'color:' . $c . ';fill:' . $c);
    mm_css_background($ctx, $s, 'background', $b);
    $c = mm_css_color($s, 'hover_color'); if ($c !== '') mm_css_add($ctx, '', "$b:hover,$b:focus", 'color:' . $c . ';fill:' . $c);
    $c = mm_css_color($s, 'button_background_hover_color'); if ($c !== '') mm_css_add($ctx, '', "$b:hover,$b:focus", 'background-color:' . $c . ';background-image:none');
    if (!empty($s['button_background_hover_background'])) {
        $tmp = array();
        foreach ($s as $k => $v) if (strpos($k, 'button_background_hover_') === 0) $tmp['background_' . substr($k, strlen('button_background_hover_'))] = $v;
        if (isset($s['__globals__'])) $tmp['__globals__'] = $s['__globals__'];
        mm_css_background($ctx, $tmp, 'background', "$b:hover,$b:focus");
    }
    $c = mm_css_color($s, 'button_hover_border_color'); if ($c !== '') mm_css_add($ctx, '', "$b:hover,$b:focus", 'border-color:' . $c);
    mm_css_border($ctx, $s, 'border', $b);
    mm_css_spacing($ctx, $s, 'text_padding', 'padding', $b);
    mm_css_shadow($ctx, $s, 'button_box_shadow', $b);
    $ind = mm_css_dim(isset($s['icon_indent']) ? $s['icon_indent'] : null);
    if ($ind !== '') mm_css_add($ctx, '', "$sel .elementor-button .elementor-align-icon-right", 'margin-left:' . $ind);
    if ($ind !== '') mm_css_add($ctx, '', "$sel .elementor-button .elementor-align-icon-left", 'margin-right:' . $ind);

    $content = ($iconAlign === 'right')
        ? '<span class="elementor-button-text">' . wp_kses_post($text) . '</span>' . $iconHtml
        : $iconHtml . '<span class="elementor-button-text">' . wp_kses_post($text) . '</span>';

    return '<div class="elementor-button-wrapper"><' . $tag . $attrs . $btnId . ' class="elementor-button elementor-button-link elementor-size-' . mm_w_attr($size) . '">'
         . '<span class="elementor-button-content-wrapper">' . $content . '</span></' . $tag . '></div>';
}

function mm_w_image_widget($s, &$ctx, $sel) {
    $img = isset($s['image']) ? $s['image'] : null;
    if (!is_array($img) || empty($img['url'])) return '';
    $size = mm_w_image_size($s);
    $html = mm_w_image($img, is_array($size) ? 'full' : $size);
    if (is_array($size)) $html = preg_replace('/\swidth="\d+"|\sheight="\d+"/', '', $html);

    $linkTo = !empty($s['link_to']) ? $s['link_to'] : 'none';
    if ($linkTo === 'file')  $html = '<a href="' . esc_url($img['url']) . '" data-elementor-open-lightbox="no">' . $html . '</a>';
    if ($linkTo === 'custom' && !empty($s['link']['url'])) $html = '<a' . mm_w_link_attrs($s['link']) . '>' . $html . '</a>';

    $cap = '';
    if (!empty($s['caption_source']) && $s['caption_source'] !== 'none') {
        $text = ($s['caption_source'] === 'custom') ? (isset($s['caption']) ? $s['caption'] : '') :
                (!empty($img['id']) ? wp_get_attachment_caption((int) $img['id']) : '');
        if ($text) $cap = '<figcaption class="widget-image-caption wp-caption-text">' . wp_kses_post($text) . '</figcaption>';
    }

    mm_css_align($ctx, $s, 'align', $sel);
    $i = "$sel img";
    foreach (mm_css_devices() as $d) {
        $w = mm_css_dim(isset($s['width' . $d]) ? $s['width' . $d] : null, '%');  if ($w !== '') mm_css_add($ctx, $d, $i, 'width:' . $w);
        $mw = mm_css_dim(isset($s['space' . $d]) ? $s['space' . $d] : null, '%'); if ($mw !== '') mm_css_add($ctx, $d, $i, 'max-width:' . $mw);
        $h = mm_css_dim(isset($s['height' . $d]) ? $s['height' . $d] : null);    if ($h !== '') mm_css_add($ctx, $d, $i, 'height:' . $h);
        if (!empty($s['object-fit' . $d])) mm_css_add($ctx, $d, $i, 'object-fit:' . $s['object-fit' . $d]);
        if (!empty($s['object-position' . $d])) mm_css_add($ctx, $d, $i, 'object-position:' . $s['object-position' . $d]);
    }
    $op = mm_css_dim(isset($s['opacity']) ? $s['opacity'] : null, ''); if ($op !== '') mm_css_add($ctx, '', $i, 'opacity:' . $op);
    mm_css_border($ctx, $s, 'image_border', $i);
    mm_css_spacing($ctx, $s, 'image_border_radius', 'border-radius', $i);
    mm_css_shadow($ctx, $s, 'image_box_shadow', $i);
    if (!empty($s['css_filters_css_filter'])) { /* filters rarely used; skipped */ }

    return $cap ? '<figure class="wp-caption">' . $html . $cap . '</figure>' : $html;
}

function mm_w_icon_box($s, &$ctx, $sel) {
    $pos = !empty($s['position']) ? $s['position'] : 'top';
    $view = !empty($s['view']) ? $s['view'] : 'default';
    $shape = !empty($s['shape']) ? $s['shape'] : 'circle';
    $tag = mm_w_heading_tag(isset($s['title_size']) ? $s['title_size'] : 'h3', 'h3');
    $icon = mm_w_icon(isset($s['selected_icon']) ? $s['selected_icon'] : null);
    $hasLink = !empty($s['link']['url']);
    $la = $hasLink ? mm_w_link_attrs($s['link']) : '';
    $title = isset($s['title_text']) ? $s['title_text'] : '';
    $desc  = isset($s['description_text']) ? $s['description_text'] : '';

    $iconHtml = $icon ? '<div class="elementor-icon-box-icon">' . ($hasLink ? "<a$la class=\"elementor-icon elementor-view-$view elementor-shape-$shape\">" : "<span class=\"elementor-icon elementor-view-$view elementor-shape-$shape\">")
              . $icon . ($hasLink ? '</a>' : '</span>') . '</div>' : '';
    $titleHtml = $title !== '' ? "<$tag class=\"elementor-icon-box-title\">" . ($hasLink ? "<a$la>" : '') . wp_kses_post($title) . ($hasLink ? '</a>' : '') . "</$tag>" : '';
    $descHtml  = $desc !== '' ? '<p class="elementor-icon-box-description">' . wp_kses_post($desc) . '</p>' : '';

    // css
    $ic = "$sel .elementor-icon";
    $c = mm_css_color($s, 'primary_color');
    if ($c !== '') {
        if ($view === 'stacked') mm_css_add($ctx, '', $ic, 'background-color:' . $c . ';color:#fff;fill:#fff');
        elseif ($view === 'framed') mm_css_add($ctx, '', $ic, 'color:' . $c . ';fill:' . $c . ';border-color:' . $c);
        else mm_css_add($ctx, '', $ic, 'color:' . $c . ';fill:' . $c);
    }
    $c = mm_css_color($s, 'secondary_color');
    if ($c !== '') {
        if ($view === 'stacked') mm_css_add($ctx, '', $ic, 'color:' . $c . ';fill:' . $c);
        elseif ($view === 'framed') mm_css_add($ctx, '', $ic, 'background-color:' . $c);
    }
    $c = mm_css_color($s, 'hover_primary_color'); if ($c !== '') mm_css_add($ctx, '', "$sel .elementor-icon-box-wrapper:hover .elementor-icon", ($view === 'stacked' ? 'background-color:' : 'color:') . $c);
    foreach (mm_css_devices() as $d) {
        $sz = mm_css_dim(isset($s['icon_size' . $d]) ? $s['icon_size' . $d] : null); if ($sz !== '') mm_css_add($ctx, $d, $ic, 'font-size:' . $sz);
        $sp = mm_css_dim(isset($s['icon_space' . $d]) ? $s['icon_space' . $d] : null);
        if ($sp !== '') mm_css_add($ctx, $d, "$sel .elementor-icon-box-icon", ($pos === 'top' ? 'margin-bottom:' : ($pos === 'left' ? 'margin-right:' : 'margin-left:')) . $sp);
        $pd = mm_css_dim(isset($s['icon_padding' . $d]) ? $s['icon_padding' . $d] : null, 'em'); if ($pd !== '') mm_css_add($ctx, $d, $ic, 'padding:' . $pd);
        $ts = mm_css_dim(isset($s['title_bottom_space' . $d]) ? $s['title_bottom_space' . $d] : null); if ($ts !== '') mm_css_add($ctx, $d, "$sel .elementor-icon-box-title", 'margin-bottom:' . $ts);
    }
    mm_css_spacing($ctx, $s, 'border_radius', 'border-radius', $ic);
    mm_css_border($ctx, $s, 'border', $ic);
    $c = mm_css_color($s, 'title_color'); if ($c !== '') mm_css_add($ctx, '', "$sel .elementor-icon-box-title, $sel .elementor-icon-box-title a", 'color:' . $c);
    $c = mm_css_color($s, 'description_color'); if ($c !== '') mm_css_add($ctx, '', "$sel .elementor-icon-box-description", 'color:' . $c);
    mm_css_typography($ctx, $s, 'title_typography', "$sel .elementor-icon-box-title");
    mm_css_typography($ctx, $s, 'description_typography', "$sel .elementor-icon-box-description");
    mm_css_align($ctx, $s, 'text_align', "$sel .elementor-icon-box-wrapper");
    if (!empty($s['content_vertical_alignment'])) mm_css_add($ctx, '', "$sel .elementor-icon-box-wrapper", 'align-items:' . $s['content_vertical_alignment']);

    return "<div class=\"elementor-icon-box-wrapper elementor-position-$pos\">$iconHtml<div class=\"elementor-icon-box-content\">$titleHtml$descHtml</div></div>";
}

function mm_w_image_box($s, &$ctx, $sel) {
    $pos = !empty($s['position']) ? $s['position'] : 'top';
    $tag = mm_w_heading_tag(isset($s['title_size']) ? $s['title_size'] : 'h3', 'h3');
    $hasLink = !empty($s['link']['url']);
    $la = $hasLink ? mm_w_link_attrs($s['link']) : '';
    $img = mm_w_image(isset($s['image']) ? $s['image'] : null, is_array($sz = mm_w_image_size($s, 'thumbnail_size')) ? 'full' : $sz);
    $title = isset($s['title_text']) ? $s['title_text'] : '';
    $desc  = isset($s['description_text']) ? $s['description_text'] : '';
    $figure = $img ? '<figure class="elementor-image-box-img">' . ($hasLink ? "<a$la>$img</a>" : $img) . '</figure>' : '';
    $t = $title !== '' ? "<$tag class=\"elementor-image-box-title\">" . ($hasLink ? "<a$la>" : '') . wp_kses_post($title) . ($hasLink ? '</a>' : '') . "</$tag>" : '';
    $dHtml = $desc !== '' ? '<p class="elementor-image-box-description">' . wp_kses_post($desc) . '</p>' : '';

    foreach (mm_css_devices() as $d) {
        $w = mm_css_dim(isset($s['image_size' . $d]) ? $s['image_size' . $d] : null, '%'); if ($w !== '') mm_css_add($ctx, $d, "$sel .elementor-image-box-img", 'width:' . $w);
        $sp = mm_css_dim(isset($s['image_space' . $d]) ? $s['image_space' . $d] : null);
        if ($sp !== '') mm_css_add($ctx, $d, "$sel .elementor-image-box-img", ($pos === 'top' ? 'margin-bottom:' : ($pos === 'left' ? 'margin-right:' : 'margin-left:')) . $sp);
    }
    mm_css_spacing($ctx, $s, 'image_border_radius', 'border-radius', "$sel .elementor-image-box-img img");
    $c = mm_css_color($s, 'title_color'); if ($c !== '') mm_css_add($ctx, '', "$sel .elementor-image-box-title, $sel .elementor-image-box-title a", 'color:' . $c);
    $c = mm_css_color($s, 'description_color'); if ($c !== '') mm_css_add($ctx, '', "$sel .elementor-image-box-description", 'color:' . $c);
    mm_css_typography($ctx, $s, 'title_typography', "$sel .elementor-image-box-title");
    mm_css_typography($ctx, $s, 'description_typography', "$sel .elementor-image-box-description");
    mm_css_align($ctx, $s, 'text_align', "$sel .elementor-image-box-wrapper");
    if (!empty($s['content_vertical_alignment'])) mm_css_add($ctx, '', "$sel .elementor-image-box-wrapper", 'align-items:' . $s['content_vertical_alignment']);

    return "<div class=\"elementor-image-box-wrapper elementor-position-$pos\">$figure<div class=\"elementor-image-box-content\">$t$dHtml</div></div>";
}

function mm_w_icon_list($s, &$ctx, $sel) {
    $items = isset($s['icon_list']) && is_array($s['icon_list']) ? $s['icon_list'] : array();
    $inline = (!empty($s['view']) && $s['view'] === 'inline');
    $html = '<ul class="elementor-icon-list-items' . ($inline ? ' elementor-inline-items' : '') . '">';
    foreach ($items as $it) {
        $icon = mm_w_icon(isset($it['selected_icon']) ? $it['selected_icon'] : null);
        $text = isset($it['text']) ? $it['text'] : '';
        $inner = ($icon ? '<span class="elementor-icon-list-icon">' . $icon . '</span>' : '') . '<span class="elementor-icon-list-text">' . wp_kses_post($text) . '</span>';
        $html .= '<li class="elementor-icon-list-item' . ($inline ? ' elementor-inline-item' : '') . '">' . (!empty($it['link']['url']) ? '<a' . mm_w_link_attrs($it['link']) . '>' . $inner . '</a>' : $inner) . '</li>';
    }
    $html .= '</ul>';

    $c = mm_css_color($s, 'icon_color'); if ($c !== '') mm_css_add($ctx, '', "$sel .elementor-icon-list-icon i", 'color:' . $c);
    $c = mm_css_color($s, 'icon_color_hover'); if ($c !== '') mm_css_add($ctx, '', "$sel .elementor-icon-list-item:hover .elementor-icon-list-icon i", 'color:' . $c);
    $c = mm_css_color($s, 'text_color'); if ($c !== '') mm_css_add($ctx, '', "$sel .elementor-icon-list-text, $sel .elementor-icon-list-item a", 'color:' . $c);
    $c = mm_css_color($s, 'text_color_hover'); if ($c !== '') mm_css_add($ctx, '', "$sel .elementor-icon-list-item:hover .elementor-icon-list-text", 'color:' . $c);
    foreach (mm_css_devices() as $d) {
        $sz = mm_css_dim(isset($s['icon_size' . $d]) ? $s['icon_size' . $d] : null);
        if ($sz !== '') mm_css_add($ctx, $d, "$sel .elementor-icon-list-icon", '--e-icon-list-icon-size:' . $sz);
        $sb = mm_css_dim(isset($s['space_between' . $d]) ? $s['space_between' . $d] : null);
        if ($sb !== '') mm_css_add($ctx, $d, "$sel .elementor-icon-list-items", $inline ? 'gap:' . $sb : '--mm-icon-list-space:' . $sb);
        $ti = mm_css_dim(isset($s['text_indent' . $d]) ? $s['text_indent' . $d] : null);
        if ($ti !== '') mm_css_add($ctx, $d, "$sel .elementor-icon-list-text", 'padding-left:' . $ti);
        if (!empty($s['icon_align' . $d])) mm_css_add($ctx, $d, "$sel .elementor-icon-list-item", 'justify-content:' . str_replace(array('left', 'right'), array('flex-start', 'flex-end'), $s['icon_align' . $d]));
    }
    mm_css_typography($ctx, $s, 'icon_typography', "$sel .elementor-icon-list-item");
    if (!empty($s['divider'])) {
        $dc = mm_css_color($s, 'divider_color') ?: '#ddd';
        $dw = mm_css_dim(isset($s['divider_weight']) ? $s['divider_weight'] : null) ?: '1px';
        mm_css_add($ctx, '', "$sel .elementor-icon-list-item:not(:last-child)", "border-bottom:$dw " . (!empty($s['divider_style']) ? $s['divider_style'] : 'solid') . " $dc");
    }
    return $html;
}

function mm_w_social_icons($s, &$ctx, $sel) {
    $items = isset($s['social_icon_list']) && is_array($s['social_icon_list']) ? $s['social_icon_list'] : array();
    $shape = !empty($s['shape']) ? $s['shape'] : 'rounded';
    $brand = array('facebook' => '#3b5998', 'facebook-f' => '#3b5998', 'twitter' => '#1da1f2', 'x-twitter' => '#000', 'youtube' => '#cd201f',
                   'instagram' => '#262626', 'tiktok' => '#000', 'linkedin' => '#0077b5', 'linkedin-in' => '#0077b5', 'pinterest' => '#bd081c',
                   'whatsapp' => '#25d366', 'telegram' => '#2ca5e0', 'envelope' => '#ea4335', 'google' => '#dd4b39');
    $html = '<div class="elementor-social-icons-wrapper elementor-grid">';
    foreach ($items as $i => $it) {
        $val = isset($it['social_icon']['value']) && is_string($it['social_icon']['value']) ? $it['social_icon']['value'] : '';
        $name = preg_replace('/^.*fa-/', '', $val);
        $id = isset($it['_id']) ? $it['_id'] : (string) $i;
        $la = !empty($it['link']['url']) ? mm_w_link_attrs($it['link']) : ' href="#"';
        $html .= '<span class="elementor-grid-item"><a class="elementor-icon elementor-social-icon elementor-social-icon-' . mm_w_attr($name) . ' elementor-repeater-item-' . mm_w_attr($id) . '"' . $la . '>'
               . '<span class="elementor-screen-only">' . mm_w_attr(ucfirst($name)) . '</span>' . mm_w_icon($it['social_icon']) . '</a></span>';
        // default = official colour, unless custom colours set
        if (empty($s['icon_color']) || $s['icon_color'] === 'default') {
            $bg = isset($brand[$name]) ? $brand[$name] : '#69727d';
            $custom = isset($it['item_icon_color']) && $it['item_icon_color'] === 'custom';
            if ($custom && !empty($it['item_icon_primary_color'])) $bg = $it['item_icon_primary_color'];
            mm_css_add($ctx, '', "$sel .elementor-repeater-item-" . $id, 'background-color:' . $bg);
            if ($custom && !empty($it['item_icon_secondary_color'])) mm_css_add($ctx, '', "$sel .elementor-repeater-item-$id i", 'color:' . $it['item_icon_secondary_color']);
        }
    }
    $html .= '</div>';

    $a = "$sel .elementor-social-icon";
    if (!empty($s['icon_color']) && $s['icon_color'] === 'custom') {
        $c = mm_css_color($s, 'icon_primary_color');   if ($c !== '') mm_css_add($ctx, '', $a, 'background-color:' . $c);
        $c = mm_css_color($s, 'icon_secondary_color'); if ($c !== '') mm_css_add($ctx, '', "$a i", 'color:' . $c);
    }
    $c = mm_css_color($s, 'hover_primary_color');   if ($c !== '') mm_css_add($ctx, '', "$a:hover", 'background-color:' . $c);
    $c = mm_css_color($s, 'hover_secondary_color'); if ($c !== '') mm_css_add($ctx, '', "$a:hover i", 'color:' . $c);
    foreach (mm_css_devices() as $d) {
        $sz = mm_css_dim(isset($s['icon_size' . $d]) ? $s['icon_size' . $d] : null);      if ($sz !== '') mm_css_add($ctx, $d, $a, '--icon-size:' . $sz);
        $pd = mm_css_dim(isset($s['icon_padding' . $d]) ? $s['icon_padding' . $d] : null, 'em'); if ($pd !== '') mm_css_add($ctx, $d, $a, '--icon-padding:' . $pd);
        $sp = mm_css_dim(isset($s['icon_spacing' . $d]) ? $s['icon_spacing' . $d] : null);  if ($sp !== '') mm_css_add($ctx, $d, "$sel .elementor-social-icons-wrapper", 'gap:' . $sp);
        if (!empty($s['align' . $d])) mm_css_add($ctx, $d, "$sel .elementor-social-icons-wrapper", 'justify-content:' . str_replace(array('left', 'right'), array('flex-start', 'flex-end'), $s['align' . $d]));
    }
    if ($shape === 'circle') mm_css_add($ctx, '', $a, 'border-radius:50%');
    if ($shape === 'square') mm_css_add($ctx, '', $a, 'border-radius:0');
    mm_css_spacing($ctx, $s, 'border_radius', 'border-radius', $a);
    mm_css_border($ctx, $s, 'image_border', $a);
    return $html;
}

function mm_w_icon_widget($s, &$ctx, $sel) {
    $view = !empty($s['view']) ? $s['view'] : 'default';
    $shape = !empty($s['shape']) ? $s['shape'] : 'circle';
    $icon = mm_w_icon(isset($s['selected_icon']) ? $s['selected_icon'] : null);
    $hasLink = !empty($s['link']['url']);
    $tag = $hasLink ? 'a' : 'div';
    $la = $hasLink ? mm_w_link_attrs($s['link']) : '';
    $ic = "$sel .elementor-icon";
    $c = mm_css_color($s, 'primary_color');
    if ($c !== '') mm_css_add($ctx, '', $ic, ($view === 'stacked' ? 'background-color:' . $c . ';color:#fff' : 'color:' . $c . ';fill:' . $c . ($view === 'framed' ? ';border-color:' . $c : '')));
    $c = mm_css_color($s, 'secondary_color'); if ($c !== '') mm_css_add($ctx, '', $ic, ($view === 'stacked' ? 'color:' . $c : 'background-color:' . $c));
    $c = mm_css_color($s, 'hover_primary_color'); if ($c !== '') mm_css_add($ctx, '', "$ic:hover", ($view === 'stacked' ? 'background-color:' : 'color:') . $c);
    foreach (mm_css_devices() as $d) {
        $sz = mm_css_dim(isset($s['size' . $d]) ? $s['size' . $d] : null); if ($sz !== '') mm_css_add($ctx, $d, $ic, 'font-size:' . $sz);
        $pd = mm_css_dim(isset($s['icon_padding' . $d]) ? $s['icon_padding' . $d] : null, 'em'); if ($pd !== '') mm_css_add($ctx, $d, $ic, 'padding:' . $pd);
        if (!empty($s['align' . $d])) mm_css_add($ctx, $d, "$sel .elementor-icon-wrapper", 'text-align:' . $s['align' . $d]);
    }
    mm_css_spacing($ctx, $s, 'border_radius', 'border-radius', $ic);
    if (!empty($s['rotate'])) { $r = mm_css_dim($s['rotate'], 'deg'); if ($r) mm_css_add($ctx, '', "$ic i", 'transform:rotate(' . $r . ')'); }
    return "<div class=\"elementor-icon-wrapper\"><$tag$la class=\"elementor-icon elementor-view-$view elementor-shape-$shape\">$icon</$tag></div>";
}

function mm_w_divider($s, &$ctx, $sel) {
    $style = !empty($s['style']) ? $s['style'] : 'solid';
    $sep = "$sel .elementor-divider-separator";
    $wt = mm_css_dim(isset($s['weight']) ? $s['weight'] : null) ?: '1px';
    $c = mm_css_color($s, 'color') ?: '#000';
    mm_css_add($ctx, '', $sep, "border-top:$wt " . (in_array($style, array('solid', 'double', 'dotted', 'dashed'), true) ? $style : 'solid') . " $c");
    foreach (mm_css_devices() as $d) {
        $w = mm_css_dim(isset($s['width' . $d]) ? $s['width' . $d] : null, '%'); if ($w !== '') mm_css_add($ctx, $d, $sep, 'width:' . $w);
        $g = mm_css_dim(isset($s['gap' . $d]) ? $s['gap' . $d] : null); if ($g !== '') mm_css_add($ctx, $d, "$sel .elementor-divider", 'padding-top:' . $g . ';padding-bottom:' . $g);
        if (!empty($s['align' . $d])) mm_css_add($ctx, $d, "$sel .elementor-divider", 'justify-content:' . str_replace(array('left', 'right'), array('flex-start', 'flex-end'), $s['align' . $d]));
    }
    return '<div class="elementor-divider"><span class="elementor-divider-separator"></span></div>';
}

function mm_w_spacer($s, &$ctx, $sel) {
    foreach (mm_css_devices() as $d) {
        $h = mm_css_dim(isset($s['space' . $d]) ? $s['space' . $d] : null);
        if ($h !== '') mm_css_add($ctx, $d, "$sel .elementor-spacer-inner", 'height:' . $h);
    }
    return '<div class="elementor-spacer"><div class="elementor-spacer-inner"></div></div>';
}

function mm_w_html($s, &$ctx, $sel) {
    return isset($s['html']) ? (string) $s['html'] : '';
}

function mm_w_shortcode($s, &$ctx, $sel) {
    return '<div class="elementor-shortcode">' . do_shortcode(isset($s['shortcode']) ? $s['shortcode'] : '') . '</div>';
}

function mm_w_video($s, &$ctx, $sel) {
    $type = !empty($s['video_type']) ? $s['video_type'] : 'youtube';
    $auto = !empty($s['autoplay']); $mute = !empty($s['mute']); $loop = !empty($s['loop']);
    $controls = !isset($s['controls']) || $s['controls'] !== '' ? true : false;
    if (isset($s['controls']) && $s['controls'] === '') $controls = false;
    $ratio = !empty($s['aspect_ratio']) ? $s['aspect_ratio'] : '169';
    $ratios = array('169' => '56.25%', '219' => '42.85%', '43' => '75%', '32' => '66.66%', '11' => '100%', '916' => '177.77%');
    mm_css_add($ctx, '', "$sel .elementor-video-wrap", 'padding-bottom:' . (isset($ratios[$ratio]) ? $ratios[$ratio] : '56.25%'));
    $media = '';
    if ($type === 'hosted' && !empty($s['hosted_url']['url'])) {
        $media = '<video class="elementor-video" src="' . esc_url($s['hosted_url']['url']) . '"' . ($controls ? ' controls' : '') . ($auto ? ' autoplay' : '') . ($mute ? ' muted' : '') . ($loop ? ' loop' : '') . ' playsinline preload="metadata"' . (!empty($s['image_overlay']['url']) ? ' poster="' . esc_url($s['image_overlay']['url']) . '"' : '') . '></video>';
    } else {
        $src = '';
        if ($type === 'youtube' && !empty($s['youtube_url']) && preg_match('~(?:v=|youtu\.be/|embed/|shorts/)([A-Za-z0-9_\-]{6,})~', $s['youtube_url'], $m)) {
            $q = array('rel' => 0, 'controls' => $controls ? 1 : 0, 'autoplay' => $auto ? 1 : 0, 'mute' => $mute ? 1 : 0, 'loop' => $loop ? 1 : 0, 'playlist' => $m[1]);
            $src = 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?' . http_build_query($q);
        } elseif ($type === 'vimeo' && !empty($s['vimeo_url']) && preg_match('~vimeo\.com/(?:video/)?(\d+)~', $s['vimeo_url'], $m)) {
            $src = 'https://player.vimeo.com/video/' . $m[1] . '?' . http_build_query(array('autoplay' => $auto ? 1 : 0, 'muted' => $mute ? 1 : 0, 'loop' => $loop ? 1 : 0));
        }
        if ($src !== '') $media = '<iframe class="elementor-video-iframe" src="' . esc_url($src) . '" loading="lazy" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen title="video"></iframe>';
    }
    if ($media === '') return '';
    $overlay = '';
    if (!empty($s['show_image_overlay']) && !empty($s['image_overlay']['url'])) {
        $overlay = '<div class="elementor-custom-embed-image-overlay mm-video-overlay" style="background-image:url(' . esc_url($s['image_overlay']['url']) . ')" role="button" tabindex="0" aria-label="Play video">'
                 . (!empty($s['show_play_icon']) || !isset($s['show_play_icon']) ? '<div class="elementor-custom-embed-play"><i class="far fa-play-circle" aria-hidden="true"></i></div>' : '') . '</div>';
        // media is deferred into a template until the overlay is clicked
        return '<div class="elementor-wrapper elementor-open-inline"><div class="elementor-video-wrap mm-video-lazy"><template class="mm-video-src">' . $media . '</template>' . $overlay . '</div></div>';
    }
    return '<div class="elementor-wrapper elementor-open-inline"><div class="elementor-video-wrap">' . $media . '</div></div>';
}

function mm_w_google_maps($s, &$ctx, $sel) {
    $addr = isset($s['address']) ? $s['address'] : '';
    if ($addr === '') return '';
    $zoom = isset($s['zoom']['size']) ? (int) $s['zoom']['size'] : 10;
    foreach (mm_css_devices() as $d) {
        $h = mm_css_dim(isset($s['height' . $d]) ? $s['height' . $d] : null); if ($h !== '') mm_css_add($ctx, $d, "$sel iframe", 'height:' . $h);
    }
    $src = 'https://maps.google.com/maps?q=' . rawurlencode($addr) . '&t=m&z=' . $zoom . '&output=embed&iwloc=near';
    return '<div class="elementor-custom-embed"><iframe loading="lazy" src="' . esc_url($src) . '" title="' . mm_w_attr($addr) . '" aria-label="' . mm_w_attr($addr) . '"></iframe></div>';
}

/* ------------------------------------------------------------------ */
/* ElementsKit / Polylang / CF7                                        */
/* ------------------------------------------------------------------ */

/** Menu for the current language: the widget's own setting first, then a Polylang-aware fallback. */
function mm_w_pick_menu($s) {
    foreach (array('ekit_nav_menu', 'menu', 'nav_menu', 'ekit_nav_menu_id') as $k) {
        if (!empty($s[$k]) && is_string($s[$k])) {
            $m = wp_get_nav_menu_object($s[$k]);
            if ($m) return $m->term_id;
        }
    }
    // fallback: the menu that holds the known language-specific parent items
    $lang = function_exists('pll_current_language') ? pll_current_language('slug') : 'de';
    $anchor = ($lang === 'en') ? 4612 : 442;   // "Piercing" item in EN / DE menu
    if (function_exists('mm_menu_id_of_item')) { $id = mm_menu_id_of_item($anchor); if ($id) return $id; }
    $menus = wp_get_nav_menus();
    return $menus ? $menus[0]->term_id : 0;
}

function mm_w_ekit_nav_menu($s, &$ctx, $sel) {
    $menu_id = mm_w_pick_menu($s);
    if (!$menu_id) return '';
    $list = wp_nav_menu(array(
        'menu' => $menu_id, 'container' => false, 'echo' => false, 'fallback_cb' => '__return_empty_string',
        'menu_class' => 'mm-nav__list', 'items_wrap' => '<ul id="%1$s" class="%2$s">%3$s</ul>', 'depth' => 0,
    ));
    if (!$list) return '';
    // colours from the widget, when set
    $map = array('ekit_menu_item_color' => "$sel .mm-nav__list > li > a|color", 'ekit_menu_item_hover_color' => "$sel .mm-nav__list > li > a:hover|color",
                 'ekit_menu_item_active_color' => "$sel .mm-nav__list > li.current-menu-item > a|color",
                 'ekit_menu_dropdown_background_color' => "$sel .mm-nav__list ul|background-color",
                 'ekit_menu_dropdown_item_color' => "$sel .mm-nav__list ul a|color", 'ekit_menu_dropdown_item_hover_color' => "$sel .mm-nav__list ul a:hover|color");
    foreach ($map as $k => $target) {
        $c = mm_css_color($s, $k); if ($c !== '') { list($selector, $prop) = explode('|', $target); mm_css_add($ctx, '', $selector, "$prop:$c"); }
    }
    mm_css_typography($ctx, $s, 'ekit_menu_typography', "$sel .mm-nav__list > li > a");
    mm_css_typography($ctx, $s, 'ekit_menu_dropdown_typography', "$sel .mm-nav__list ul a");
    $label = function_exists('pll_current_language') && pll_current_language('slug') === 'de' ? 'Menü' : 'Menu';
    return '<nav class="mm-nav" aria-label="' . mm_w_attr($label) . '"><button class="mm-nav__toggle" type="button" aria-expanded="false" aria-controls="mm-nav-' . $menu_id . '"><span class="mm-nav__bar"></span><span class="mm-nav__bar"></span><span class="mm-nav__bar"></span><span class="elementor-screen-only">' . mm_w_attr($label) . '</span></button>'
         . '<div class="mm-nav__panel" id="mm-nav-' . $menu_id . '">' . $list . '</div></nav>';
}

function mm_w_polylang_switcher($s, &$ctx, $sel) {
    if (!function_exists('pll_the_languages')) return '';
    $langs = pll_the_languages(array('raw' => 1, 'hide_if_no_translation' => 0, 'hide_current' => 0));
    if (!is_array($langs) || !$langs) return '';
    $cur = ''; $items = '';
    foreach ($langs as $l) {
        $code = strtoupper(isset($l['slug']) ? $l['slug'] : '');
        $a = '<a lang="' . mm_w_attr($l['locale']) . '" hreflang="' . mm_w_attr($l['locale']) . '" href="' . esc_url($l['url']) . '">' . mm_w_attr($code) . '</a>';
        if (!empty($l['current_lang'])) $cur = $a; else $items .= '<li>' . $a . '</li>';
    }
    return '<div class="mm-lang"><button class="mm-lang__toggle" type="button" aria-expanded="false">' . ($cur ?: '<span>' . mm_w_attr(strtoupper(pll_current_language('slug'))) . '</span>') . '<span class="mm-lang__caret" aria-hidden="true"></span></button><ul class="mm-lang__list">' . $items . '</ul></div>';
}

function mm_w_ekit_cf7($s, &$ctx, $sel) {
    $id = 0;
    foreach ($s as $k => $v) {
        if (strpos($k, 'contact_form') !== false && is_scalar($v) && (int) $v > 0) { $id = (int) $v; break; }
    }
    if (!$id) return '';
    return '<div class="mm-cf7">' . do_shortcode('[contact-form-7 id="' . $id . '"]') . '</div>';
}

function mm_w_ekit_accordion($s, &$ctx, $sel) {
    $items = array();
    foreach ($s as $k => $v) { if (strpos($k, 'accordion') !== false && is_array($v) && isset($v[0]) && is_array($v[0])) { $items = $v; break; } }
    if (!$items) return '';
    $html = '<div class="mm-accordion">';
    foreach ($items as $i => $it) {
        $title = ''; $content = '';
        foreach ($it as $k => $v) {
            if (!is_string($v)) continue;
            if ($title === '' && strpos($k, 'title') !== false) $title = $v;
            elseif ($content === '' && strpos($k, 'content') !== false) $content = $v;
        }
        $html .= '<details class="mm-accordion__item"' . ($i === 0 && !empty($s['ekit_accordion_open_first']) ? ' open' : '') . '><summary class="mm-accordion__title">' . wp_kses_post($title) . '<i class="fas fa-plus mm-accordion__icon" aria-hidden="true"></i></summary><div class="mm-accordion__body">' . do_shortcode(wp_kses_post($content)) . '</div></details>';
    }
    return $html . '</div>';
}

function mm_w_premium_media_wheel($s, &$ctx, $sel) {
    $items = array();
    foreach ($s as $k => $v) { if (strpos($k, 'media_wheel') !== false && is_array($v) && isset($v[0]) && is_array($v[0])) { $items = $v; break; } }
    if (!$items) return '';
    $html = '<div class="mm-media-grid">';
    foreach ($items as $it) {
        $img = isset($it['media_wheel_img']) ? $it['media_wheel_img'] : (isset($it['image']) ? $it['image'] : null);
        if (!is_array($img) || empty($img['url'])) continue;
        $title = isset($it['media_title']) ? $it['media_title'] : '';
        $fig = '<figure class="mm-media-grid__item">' . mm_w_image($img, 'large') . ($title !== '' ? '<figcaption>' . wp_kses_post($title) . '</figcaption>' : '') . '</figure>';
        $html .= !empty($it['media_link']['url']) ? '<a' . mm_w_link_attrs($it['media_link']) . '>' . $fig . '</a>' : $fig;
    }
    return $html . '</div>';
}

/* ------------------------------------------------------------------ */
/* Elementor Pro widgets (plugin absent) — rendered from stored data   */
/* ------------------------------------------------------------------ */

function mm_w_slides($s, &$ctx, $sel) {
    $slides = isset($s['slides']) && is_array($s['slides']) ? $s['slides'] : array();
    if (!$slides) return '';
    $h = '<div class="mm-hero" data-mm-autoplay="' . (isset($s['autoplay_speed']) ? (int) $s['autoplay_speed'] : 6000) . '"><div class="mm-hero__track">';
    $dots = '';
    foreach ($slides as $i => $sl) {
        $bg = !empty($sl['background_image']['url']) ? ' style="background-image:url(' . esc_url($sl['background_image']['url']) . ')"' : '';
        $h .= '<div class="mm-hero__slide"' . $bg . '><div class="mm-hero__content">'
            . (!empty($sl['heading']) ? '<h2 class="mm-hero__title">' . wp_kses_post($sl['heading']) . '</h2>' : '')
            . (!empty($sl['description']) ? '<div class="mm-hero__text">' . wp_kses_post($sl['description']) . '</div>' : '')
            . (!empty($sl['button_text']) ? '<a class="elementor-button mm-hero__btn"' . mm_w_link_attrs(isset($sl['link']) ? $sl['link'] : array()) . '>' . wp_kses_post($sl['button_text']) . '</a>' : '')
            . '</div></div>';
        $dots .= '<button class="mm-hero__dot' . ($i === 0 ? ' is-active' : '') . '" type="button" aria-label="Slide ' . ($i + 1) . '"></button>';
    }
    return $h . '</div><button class="mm-hero__nav mm-hero__nav--prev" type="button" aria-label="Previous">&#10094;</button><button class="mm-hero__nav mm-hero__nav--next" type="button" aria-label="Next">&#10095;</button><div class="mm-hero__dots">' . $dots . '</div></div>';
}

function mm_w_posts_archive($s, &$ctx, $sel) {
    $cols = isset($s['columns']) ? (int) $s['columns'] : 2;
    if (shortcode_exists('mm_blog_archive')) return do_shortcode('[mm_blog_archive columns="' . max(1, min(4, $cols)) . '"]');
    return '';
}

function mm_w_theme_post_title($s, &$ctx, $sel) {
    $tag = mm_w_heading_tag(isset($s['header_size']) ? $s['header_size'] : 'h1', 'h1');
    return "<$tag class=\"elementor-heading-title elementor-size-default\">" . get_the_title() . "</$tag>";
}
function mm_w_theme_post_excerpt($s, &$ctx, $sel) { return '<div class="elementor-widget-container">' . wp_kses_post(get_the_excerpt()) . '</div>'; }
function mm_w_theme_post_featured_image($s, &$ctx, $sel) { return get_the_post_thumbnail(null, 'large'); }
function mm_w_theme_post_content($s, &$ctx, $sel) {
    global $post;
    if (!$post || get_post_meta($post->ID, '_elementor_data', true)) return ''; // avoid recursion into an Elementor page
    return apply_filters('the_content', $post->post_content);
}
function mm_w_post_info($s, &$ctx, $sel) {
    return '<ul class="elementor-icon-list-items elementor-inline-items"><li class="elementor-icon-list-item"><span class="elementor-icon-list-text">' . get_the_date() . '</span></li><li class="elementor-icon-list-item"><span class="elementor-icon-list-text">' . get_the_author() . '</span></li></ul>';
}

function mm_w_price_table($s, &$ctx, $sel) {
    $feat = isset($s['features_list']) && is_array($s['features_list']) ? $s['features_list'] : array();
    $h = '<div class="mm-price"><div class="mm-price__header">' . (!empty($s['heading']) ? '<h3>' . wp_kses_post($s['heading']) . '</h3>' : '') . (!empty($s['sub_heading']) ? '<p>' . wp_kses_post($s['sub_heading']) . '</p>' : '') . '</div>';
    $h .= '<div class="mm-price__price">' . (!empty($s['currency_symbol']) ? '<span>' . mm_w_attr($s['currency_symbol'] === 'euro' ? '€' : ($s['currency_symbol'] === 'dollar' ? '$' : $s['currency_symbol'])) . '</span>' : '') . '<strong>' . (isset($s['price']) ? mm_w_attr($s['price']) : '') . '</strong>' . (!empty($s['period']) ? '<small>' . wp_kses_post($s['period']) . '</small>' : '') . '</div>';
    if ($feat) { $h .= '<ul class="mm-price__features">'; foreach ($feat as $f) $h .= '<li>' . mm_w_icon(isset($f['selected_item_icon']) ? $f['selected_item_icon'] : null) . ' ' . wp_kses_post(isset($f['item_text']) ? $f['item_text'] : '') . '</li>'; $h .= '</ul>'; }
    if (!empty($s['button_text'])) $h .= '<div class="elementor-button-wrapper"><a class="elementor-button elementor-size-md"' . mm_w_link_attrs(isset($s['link']) ? $s['link'] : array()) . '>' . wp_kses_post($s['button_text']) . '</a></div>';
    return $h . '</div>';
}

function mm_w_image_carousel($s, &$ctx, $sel) {
    $items = isset($s['carousel']) && is_array($s['carousel']) ? $s['carousel'] : (isset($s['slides']) && is_array($s['slides']) ? $s['slides'] : array());
    if (!$items) return '';
    $h = '<div class="mm-media-grid">';
    foreach ($items as $it) {
        $img = isset($it['url']) ? $it : (isset($it['image']) ? $it['image'] : null);
        if (is_array($img) && !empty($img['url'])) $h .= '<figure class="mm-media-grid__item">' . mm_w_image($img, 'large') . '</figure>';
    }
    return $h . '</div>';
}

/* ------------------------------------------------------------------ */
/* dispatcher                                                          */
/* ------------------------------------------------------------------ */

/**
 * Render a widget's inner HTML. Returns null when the type renders its own
 * children instead (handled by the caller), '' when there is nothing to show.
 */
function mm_render_widget_inner($type, $s, &$ctx, $sel) {
    switch ($type) {
        case 'heading':            return mm_w_heading($s, $ctx, $sel);
        case 'text-editor':        return mm_w_text_editor($s, $ctx, $sel);
        case 'button':             return mm_w_button($s, $ctx, $sel);
        case 'image':              return mm_w_image_widget($s, $ctx, $sel);
        case 'icon-box':           return mm_w_icon_box($s, $ctx, $sel);
        case 'image-box':          return mm_w_image_box($s, $ctx, $sel);
        case 'icon-list':          return mm_w_icon_list($s, $ctx, $sel);
        case 'social-icons':       return mm_w_social_icons($s, $ctx, $sel);
        case 'icon':               return mm_w_icon_widget($s, $ctx, $sel);
        case 'divider':            return mm_w_divider($s, $ctx, $sel);
        case 'spacer':             return mm_w_spacer($s, $ctx, $sel);
        case 'html':               return mm_w_html($s, $ctx, $sel);
        case 'shortcode':          return mm_w_shortcode($s, $ctx, $sel);
        case 'video':              return mm_w_video($s, $ctx, $sel);
        case 'google_maps':        return mm_w_google_maps($s, $ctx, $sel);
        case 'ekit-nav-menu':      return mm_w_ekit_nav_menu($s, $ctx, $sel);
        case 'polylang-language-switcher': return mm_w_polylang_switcher($s, $ctx, $sel);
        case 'elementskit-contact-form7':  return mm_w_ekit_cf7($s, $ctx, $sel);
        case 'elementskit-accordion':      return mm_w_ekit_accordion($s, $ctx, $sel);
        case 'premium-media-wheel':        return mm_w_premium_media_wheel($s, $ctx, $sel);
        case 'slides':             return mm_w_slides($s, $ctx, $sel);
        case 'loop-carousel': case 'posts': case 'archive-posts': case 'loop-grid':
                                   return mm_w_posts_archive($s, $ctx, $sel);
        case 'theme-post-title':   return mm_w_theme_post_title($s, $ctx, $sel);
        case 'theme-post-excerpt': return mm_w_theme_post_excerpt($s, $ctx, $sel);
        case 'theme-post-featured-image': return mm_w_theme_post_featured_image($s, $ctx, $sel);
        case 'theme-post-content': return mm_w_theme_post_content($s, $ctx, $sel);
        case 'post-info':          return mm_w_post_info($s, $ctx, $sel);
        case 'price-table':        return mm_w_price_table($s, $ctx, $sel);
        case 'image-carousel': case 'media-carousel':
                                   return mm_w_image_carousel($s, $ctx, $sel);
        case 'nested-carousel': case 'nested-tabs': case 'nested-accordion':
                                   return null;   // children are rendered by the caller
        case 'form': case 'global': case 'ha-card':
                                   return '<!-- mm-render: widget "' . esc_html($type) . '" has no free equivalent -->';
    }
    // Unknown type: keep the page intact and make the gap visible in source.
    $ctx->unknown[$type] = isset($ctx->unknown[$type]) ? $ctx->unknown[$type] + 1 : 1;
    return '<!-- mm-render: unsupported widget "' . esc_html($type) . '" -->';
}
