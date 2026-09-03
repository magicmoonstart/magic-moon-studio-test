<?php
/**
 * Settings -> CSS — corrections/renderer/styles.php
 *
 * Turns the settings stored on each element in _elementor_data into CSS, the
 * job Elementor's own CSS generator used to do into uploads/elementor/css.
 * Everything here is driven by the setting keys found in the reference backup
 * (typography_*, padding/margin, background_*, border_*, flex_*, width,
 * min_height, align, colours) with the _tablet / _mobile suffixes Elementor
 * uses for responsive values.
 *
 * Global values ("__globals__": {"title_color": "globals/colors?id=primary"})
 * are resolved against the active kit so site-wide colours and fonts keep
 * their meaning.
 *
 * Nothing in this file depends on Elementor being installed.
 */

if (!defined('ABSPATH')) exit;

/* ------------------------------------------------------------------ */
/* Kit (site settings)                                                 */
/* ------------------------------------------------------------------ */

function mm_kit_settings() {
    static $kit = null;
    if ($kit !== null) return $kit;
    $kit = array();
    $id = (int) get_option('elementor_active_kit');
    if ($id) {
        $s = get_post_meta($id, '_elementor_page_settings', true);
        if (is_string($s)) $s = maybe_unserialize($s);
        if (is_array($s)) $kit = $s;
    }
    return $kit;
}

/** Resolve "globals/colors?id=xyz" to a colour string, or ''. */
function mm_kit_color($ref) {
    if (!is_string($ref) || strpos($ref, 'globals/colors?id=') !== 0) return '';
    $id = substr($ref, strlen('globals/colors?id='));
    $kit = mm_kit_settings();
    foreach (array('system_colors', 'custom_colors') as $group) {
        if (empty($kit[$group]) || !is_array($kit[$group])) continue;
        foreach ($kit[$group] as $c) {
            if (isset($c['_id']) && $c['_id'] === $id && !empty($c['color'])) return $c['color'];
        }
    }
    return '';
}

/** Resolve "globals/typography?id=xyz" to an array of typography_* settings. */
function mm_kit_typography($ref) {
    if (!is_string($ref) || strpos($ref, 'globals/typography?id=') !== 0) return array();
    $id = substr($ref, strlen('globals/typography?id='));
    $kit = mm_kit_settings();
    foreach (array('system_typography', 'custom_typography') as $group) {
        if (empty($kit[$group]) || !is_array($kit[$group])) continue;
        foreach ($kit[$group] as $t) {
            if (isset($t['_id']) && $t['_id'] === $id) return $t;
        }
    }
    return array();
}

/* ------------------------------------------------------------------ */
/* Value helpers                                                       */
/* ------------------------------------------------------------------ */

/** {size, unit} -> "12px". Returns '' when empty. */
function mm_css_dim($v, $fallbackUnit = 'px') {
    if (is_array($v)) {
        if (!isset($v['size']) || $v['size'] === '' || $v['size'] === null) return '';
        $u = !empty($v['unit']) ? $v['unit'] : $fallbackUnit;
        if ($u === 'custom') return (string) $v['size'];
        return $v['size'] . $u;
    }
    if (is_numeric($v)) return $v . $fallbackUnit;
    return '';
}

/** {top,right,bottom,left,unit} -> "1px 2px 3px 4px". Returns '' when all empty. */
function mm_css_box($v) {
    if (!is_array($v)) return '';
    $u = !empty($v['unit']) ? $v['unit'] : 'px';
    $parts = array(); $any = false;
    foreach (array('top', 'right', 'bottom', 'left') as $side) {
        $n = isset($v[$side]) ? $v[$side] : '';
        if ($n === '' || $n === null) { $parts[] = '0'; continue; }
        $any = true;
        $parts[] = ($u === 'custom') ? (string) $n : $n . $u;
    }
    return $any ? implode(' ', $parts) : '';
}

/** A colour setting, resolving globals. */
function mm_css_color($settings, $key) {
    if (!empty($settings['__globals__'][$key])) {
        $g = mm_kit_color($settings['__globals__'][$key]);
        if ($g !== '') return $g;
    }
    return (isset($settings[$key]) && is_string($settings[$key]) && $settings[$key] !== '') ? $settings[$key] : '';
}

function mm_css_esc_url($u) { return str_replace(array('"', ')', "\n", "\r"), '', (string) $u); }

/* ------------------------------------------------------------------ */
/* Responsive collection                                               */
/* ------------------------------------------------------------------ */

/** Devices: '' desktop, '_tablet' <=1024, '_mobile' <=767 (Elementor defaults). */
function mm_css_devices() { return array('', '_tablet', '_mobile'); }

/**
 * $ctx->css is array(device => array(selector => array(declarations)))
 */
function mm_css_add(&$ctx, $device, $selector, $decl) {
    if ($decl === '' || $decl === null) return;
    if (!isset($ctx->css[$device])) $ctx->css[$device] = array();
    if (!isset($ctx->css[$device][$selector])) $ctx->css[$device][$selector] = array();
    $ctx->css[$device][$selector][] = rtrim($decl, ';') . ';';
}

/** Flatten collected rules into a stylesheet string. */
function mm_css_compile($ctx) {
    $out = '';
    $wrap = array('' => '', '_tablet' => '@media (max-width: 1024px)', '_mobile' => '@media (max-width: 767px)');
    foreach ($wrap as $device => $mq) {
        if (empty($ctx->css[$device])) continue;
        $block = '';
        foreach ($ctx->css[$device] as $sel => $decls) {
            $block .= $sel . '{' . implode('', array_unique($decls)) . '}' . "\n";
        }
        $out .= $mq ? $mq . "{\n" . $block . "}\n" : $block;
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/* Typography                                                          */
/* ------------------------------------------------------------------ */

/**
 * Emit typography for $prefix (e.g. 'typography', 'title_typography').
 * Handles a global typography reference on "<prefix>_typography".
 */
function mm_css_typography(&$ctx, $settings, $prefix, $selector) {
    // global reference replaces the whole set
    if (!empty($settings['__globals__'][$prefix . '_typography'])) {
        $g = mm_kit_typography($settings['__globals__'][$prefix . '_typography']);
        if ($g) {
            $renamed = array();
            foreach ($g as $k => $v) {
                if (strpos($k, 'typography_') === 0) $renamed[$prefix . substr($k, strlen('typography'))] = $v;
            }
            mm_css_typography_apply($ctx, $renamed, $prefix, $selector);
        }
    }
    mm_css_typography_apply($ctx, $settings, $prefix, $selector);
}

function mm_css_typography_apply(&$ctx, $s, $p, $selector) {
    if (!empty($s[$p . '_font_family'])) {
        $fam = $s[$p . '_font_family'];
        $ctx->fonts[$fam] = true;
        mm_css_add($ctx, '', $selector, 'font-family:"' . addslashes($fam) . '",sans-serif');
    }
    foreach (mm_css_devices() as $d) {
        $fs = mm_css_dim(isset($s[$p . '_font_size' . $d]) ? $s[$p . '_font_size' . $d] : null);
        if ($fs !== '') mm_css_add($ctx, $d, $selector, 'font-size:' . $fs);
        $lh = mm_css_dim(isset($s[$p . '_line_height' . $d]) ? $s[$p . '_line_height' . $d] : null, 'em');
        if ($lh !== '') mm_css_add($ctx, $d, $selector, 'line-height:' . $lh);
        $ls = mm_css_dim(isset($s[$p . '_letter_spacing' . $d]) ? $s[$p . '_letter_spacing' . $d] : null);
        if ($ls !== '') mm_css_add($ctx, $d, $selector, 'letter-spacing:' . $ls);
        $ws = mm_css_dim(isset($s[$p . '_word_spacing' . $d]) ? $s[$p . '_word_spacing' . $d] : null);
        if ($ws !== '') mm_css_add($ctx, $d, $selector, 'word-spacing:' . $ws);
    }
    if (!empty($s[$p . '_font_weight']))      mm_css_add($ctx, '', $selector, 'font-weight:' . $s[$p . '_font_weight']);
    if (!empty($s[$p . '_text_transform']))   mm_css_add($ctx, '', $selector, 'text-transform:' . $s[$p . '_text_transform']);
    if (!empty($s[$p . '_font_style']))       mm_css_add($ctx, '', $selector, 'font-style:' . $s[$p . '_font_style']);
    if (!empty($s[$p . '_text_decoration']))  mm_css_add($ctx, '', $selector, 'text-decoration:' . $s[$p . '_text_decoration']);
}

/* ------------------------------------------------------------------ */
/* Shared groups: spacing, background, border, shadow                  */
/* ------------------------------------------------------------------ */

function mm_css_spacing(&$ctx, $s, $key, $prop, $selector) {
    foreach (mm_css_devices() as $d) {
        if (!isset($s[$key . $d])) continue;
        $v = mm_css_box($s[$key . $d]);
        if ($v !== '') mm_css_add($ctx, $d, $selector, $prop . ':' . $v);
    }
}

/**
 * Background (classic or gradient) for $prefix ('background' or 'background_overlay').
 */
function mm_css_background(&$ctx, $s, $prefix, $selector) {
    $type = isset($s[$prefix . '_background']) ? $s[$prefix . '_background'] : '';
    $color = mm_css_color($s, $prefix . '_color');

    if ($type === 'gradient') {
        $b     = mm_css_color($s, $prefix . '_color_b');
        $stopA = isset($s[$prefix . '_color_stop']['size']) ? $s[$prefix . '_color_stop']['size'] : 0;
        $stopB = isset($s[$prefix . '_color_b_stop']['size']) ? $s[$prefix . '_color_b_stop']['size'] : 100;
        $gtype = !empty($s[$prefix . '_gradient_type']) ? $s[$prefix . '_gradient_type'] : 'linear';
        if ($color !== '' && $b !== '') {
            if ($gtype === 'radial') {
                $pos = !empty($s[$prefix . '_gradient_position']) ? $s[$prefix . '_gradient_position'] : 'center center';
                $g = "radial-gradient(at $pos, $color {$stopA}%, $b {$stopB}%)";
            } else {
                $angle = isset($s[$prefix . '_gradient_angle']['size']) ? $s[$prefix . '_gradient_angle']['size'] : 180;
                $g = "linear-gradient({$angle}deg, $color {$stopA}%, $b {$stopB}%)";
            }
            mm_css_add($ctx, '', $selector, 'background-color:transparent;background-image:' . $g);
        }
        return;
    }

    if ($type !== 'classic' && $type !== '') return;

    if ($color !== '') mm_css_add($ctx, '', $selector, 'background-color:' . $color);

    foreach (mm_css_devices() as $d) {
        if (!empty($s[$prefix . '_image' . $d]['url'])) {
            $url = mm_css_esc_url($s[$prefix . '_image' . $d]['url']);
            mm_css_add($ctx, $d, $selector, 'background-image:url("' . $url . '")');
        }
        if (!empty($s[$prefix . '_position' . $d])) {
            $pos = $s[$prefix . '_position' . $d];
            if ($pos === 'initial') {
                $x = mm_css_dim(isset($s[$prefix . '_xpos' . $d]) ? $s[$prefix . '_xpos' . $d] : null);
                $y = mm_css_dim(isset($s[$prefix . '_ypos' . $d]) ? $s[$prefix . '_ypos' . $d] : null);
                if ($x !== '' || $y !== '') mm_css_add($ctx, $d, $selector, 'background-position:' . ($x ?: '0px') . ' ' . ($y ?: '0px'));
            } else {
                mm_css_add($ctx, $d, $selector, 'background-position:' . $pos);
            }
        }
        if (!empty($s[$prefix . '_repeat' . $d]))     mm_css_add($ctx, $d, $selector, 'background-repeat:' . $s[$prefix . '_repeat' . $d]);
        if (!empty($s[$prefix . '_attachment' . $d])) mm_css_add($ctx, $d, $selector, 'background-attachment:' . $s[$prefix . '_attachment' . $d]);
        if (!empty($s[$prefix . '_size' . $d])) {
            $sz = $s[$prefix . '_size' . $d];
            if ($sz === 'initial') {
                $w = mm_css_dim(isset($s[$prefix . '_bg_width' . $d]) ? $s[$prefix . '_bg_width' . $d] : null);
                if ($w !== '') mm_css_add($ctx, $d, $selector, 'background-size:' . $w . ' auto');
            } else {
                mm_css_add($ctx, $d, $selector, 'background-size:' . $sz);
            }
        }
    }
}

function mm_css_border(&$ctx, $s, $prefix, $selector) {
    $style = isset($s[$prefix . '_border']) ? $s[$prefix . '_border'] : '';
    if ($style !== '' && $style !== 'none') {
        mm_css_add($ctx, '', $selector, 'border-style:' . $style);
        $c = mm_css_color($s, $prefix . '_color');
        if ($c !== '') mm_css_add($ctx, '', $selector, 'border-color:' . $c);
        mm_css_spacing($ctx, $s, $prefix . '_width', 'border-width', $selector);
    } elseif ($style === 'none') {
        mm_css_add($ctx, '', $selector, 'border-style:none');
    }
    mm_css_spacing($ctx, $s, $prefix . '_radius', 'border-radius', $selector);
}

function mm_css_shadow(&$ctx, $s, $key, $selector) {
    if (empty($s[$key . '_box_shadow_type']) && empty($s[$key . '_box_shadow'])) return;
    $sh = isset($s[$key . '_box_shadow']) ? $s[$key . '_box_shadow'] : null;
    if (!is_array($sh)) return;
    $h = isset($sh['horizontal']) ? (int) $sh['horizontal'] : 0;
    $v = isset($sh['vertical']) ? (int) $sh['vertical'] : 0;
    $b = isset($sh['blur']) ? (int) $sh['blur'] : 10;
    $sp = isset($sh['spread']) ? (int) $sh['spread'] : 0;
    $c = !empty($sh['color']) ? $sh['color'] : 'rgba(0,0,0,.5)';
    $pos = (!empty($sh['position']) && $sh['position'] === 'inset') ? 'inset ' : '';
    mm_css_add($ctx, '', $selector, "box-shadow:{$pos}{$h}px {$v}px {$b}px {$sp}px {$c}");
}

/** text-align from an "align" style key, responsive. */
function mm_css_align(&$ctx, $s, $key, $selector, $prop = 'text-align') {
    foreach (mm_css_devices() as $d) {
        if (!empty($s[$key . $d]) && is_string($s[$key . $d])) {
            $v = $s[$key . $d];
            if ($prop === 'text-align' && $v === 'justify') $v = 'justify';
            mm_css_add($ctx, $d, $selector, $prop . ':' . $v);
        }
    }
}

/* ------------------------------------------------------------------ */
/* Container                                                           */
/* ------------------------------------------------------------------ */

function mm_css_container(&$ctx, $s, $selector) {
    // flex model, written as custom properties so the base stylesheet can
    // apply them to the right element (boxed containers lay out their inner)
    $map = array(
        'flex_direction'       => '--flex-direction',
        'flex_justify_content' => '--justify-content',
        'flex_align_items'     => '--align-items',
        'flex_wrap'            => '--flex-wrap',
        'flex_align_self'      => '--align-self',
        'content_position'     => '--content-position',
    );
    foreach ($map as $key => $prop) {
        foreach (mm_css_devices() as $d) {
            if (!empty($s[$key . $d]) && is_string($s[$key . $d])) {
                mm_css_add($ctx, $d, $selector, $prop . ':' . $s[$key . $d]);
            }
        }
    }
    // gap
    foreach (mm_css_devices() as $d) {
        if (isset($s['flex_gap' . $d]) && is_array($s['flex_gap' . $d])) {
            $g = $s['flex_gap' . $d];
            $u = !empty($g['unit']) ? $g['unit'] : 'px';
            $col = (isset($g['column']) && $g['column'] !== '') ? $g['column'] . $u : (isset($g['size']) && $g['size'] !== '' ? $g['size'] . $u : '');
            $row = (isset($g['row']) && $g['row'] !== '') ? $g['row'] . $u : $col;
            if ($col !== '') mm_css_add($ctx, $d, $selector, '--gap:' . $row . ' ' . $col);
        }
    }
    // sizes
    foreach (mm_css_devices() as $d) {
        $w = mm_css_dim(isset($s['width' . $d]) ? $s['width' . $d] : null, '%');
        if ($w !== '') mm_css_add($ctx, $d, $selector, '--width:' . $w);
        $mh = mm_css_dim(isset($s['min_height' . $d]) ? $s['min_height' . $d] : null);
        if ($mh !== '') mm_css_add($ctx, $d, $selector, '--min-height:' . $mh);
        $bw = mm_css_dim(isset($s['boxed_width' . $d]) ? $s['boxed_width' . $d] : null);
        if ($bw !== '') mm_css_add($ctx, $d, $selector, '--content-width:' . $bw);
    }
    // grow / shrink
    if (!empty($s['_flex_size'])) {
        if ($s['_flex_size'] === 'grow')   mm_css_add($ctx, '', $selector, '--flex-grow:1;--flex-shrink:0');
        if ($s['_flex_size'] === 'shrink') mm_css_add($ctx, '', $selector, '--flex-grow:0;--flex-shrink:1');
        if ($s['_flex_size'] === 'none')   mm_css_add($ctx, '', $selector, '--flex-grow:0;--flex-shrink:0');
    }
    if (isset($s['_flex_grow']) && $s['_flex_grow'] !== '')   mm_css_add($ctx, '', $selector, '--flex-grow:' . (int) $s['_flex_grow']);
    if (isset($s['_flex_shrink']) && $s['_flex_shrink'] !== '') mm_css_add($ctx, '', $selector, '--flex-shrink:' . (int) $s['_flex_shrink']);

    // spacing — padding/margin apply to the container element itself
    mm_css_spacing($ctx, $s, 'padding', 'padding', $selector);
    mm_css_spacing($ctx, $s, 'margin', 'margin', $selector);

    // background, overlay, border, shadow
    mm_css_background($ctx, $s, 'background', $selector);
    if (!empty($s['background_overlay_background'])) {
        mm_css_add($ctx, '', $selector, '--background-overlay:\'\'');
        mm_css_background($ctx, $s, 'background_overlay', $selector . '::before');
        $op = mm_css_dim(isset($s['background_overlay_opacity']) ? $s['background_overlay_opacity'] : null, '');
        if ($op !== '') mm_css_add($ctx, '', $selector . '::before', 'opacity:' . $op);
    }
    mm_css_border($ctx, $s, 'border', $selector);
    mm_css_shadow($ctx, $s, 'box_shadow', $selector);

    // position / overflow / z-index
    if (!empty($s['position']) && $s['position'] !== 'relative') mm_css_add($ctx, '', $selector, 'position:' . $s['position']);
    if (!empty($s['overflow']))  mm_css_add($ctx, '', $selector, 'overflow:' . $s['overflow']);
    if (isset($s['z_index']) && $s['z_index'] !== '') mm_css_add($ctx, '', $selector, 'z-index:' . (int) $s['z_index']);
    if (!empty($s['position']) && in_array($s['position'], array('absolute', 'fixed'), true)) {
        mm_css_position_offsets($ctx, $s, $selector);
    }
}

function mm_css_position_offsets(&$ctx, $s, $selector) {
    foreach (mm_css_devices() as $d) {
        $h = !empty($s['_offset_orientation_h']) ? $s['_offset_orientation_h'] : 'start';
        $v = !empty($s['_offset_orientation_v']) ? $s['_offset_orientation_v'] : 'start';
        $x = mm_css_dim(isset($s[($h === 'end' ? '_offset_x_end' : '_offset_x') . $d]) ? $s[($h === 'end' ? '_offset_x_end' : '_offset_x') . $d] : null);
        $y = mm_css_dim(isset($s[($v === 'end' ? '_offset_y_end' : '_offset_y') . $d]) ? $s[($v === 'end' ? '_offset_y_end' : '_offset_y') . $d] : null);
        if ($x !== '') mm_css_add($ctx, $d, $selector, ($h === 'end' ? 'right' : 'left') . ':' . $x);
        if ($y !== '') mm_css_add($ctx, $d, $selector, ($v === 'end' ? 'bottom' : 'top') . ':' . $y);
    }
}

/* ------------------------------------------------------------------ */
/* Widget common (advanced tab)                                        */
/* ------------------------------------------------------------------ */

function mm_css_widget_common(&$ctx, $s, $selector) {
    mm_css_spacing($ctx, $s, '_margin', 'margin', $selector);
    mm_css_spacing($ctx, $s, '_padding', 'padding', $selector . ' > .elementor-widget-container');

    foreach (mm_css_devices() as $d) {
        $w = isset($s['_element_width' . $d]) ? $s['_element_width' . $d] : '';
        if ($w === 'inherit')  mm_css_add($ctx, $d, $selector, '--container-widget-width:100%');
        if ($w === 'initial')  mm_css_add($ctx, $d, $selector, '--container-widget-width:initial;--container-widget-flex-grow:0');
        if ($w === 'auto')     mm_css_add($ctx, $d, $selector, '--container-widget-width:auto;--container-widget-flex-grow:0');
        if ($w === 'custom') {
            $cw = mm_css_dim(isset($s['_element_custom_width' . $d]) ? $s['_element_custom_width' . $d] : null, '%');
            if ($cw !== '') mm_css_add($ctx, $d, $selector, '--container-widget-width:' . $cw . ';--container-widget-flex-grow:0');
        }
        $as = isset($s['_flex_align_self' . $d]) ? $s['_flex_align_self' . $d] : '';
        if ($as !== '') mm_css_add($ctx, $d, $selector, 'align-self:' . $as);
    }
    if (!empty($s['_flex_size'])) {
        if ($s['_flex_size'] === 'grow') mm_css_add($ctx, '', $selector, '--container-widget-flex-grow:1');
        if ($s['_flex_size'] === 'none') mm_css_add($ctx, '', $selector, '--container-widget-flex-grow:0;flex-shrink:0');
    }
    if (isset($s['_z_index']) && $s['_z_index'] !== '') mm_css_add($ctx, '', $selector, 'z-index:' . (int) $s['_z_index']);
    if (!empty($s['_position']) && $s['_position'] !== 'relative') {
        mm_css_add($ctx, '', $selector, 'position:' . $s['_position']);
        mm_css_position_offsets($ctx, $s, $selector);
    }

    // background / border / shadow on the widget container (advanced tab)
    mm_css_background($ctx, $s, '_background', $selector . ' > .elementor-widget-container');
    mm_css_border($ctx, $s, '_border', $selector . ' > .elementor-widget-container');
    mm_css_shadow($ctx, $s, '_box_shadow', $selector . ' > .elementor-widget-container');

    // hidden per device
    if (!empty($s['hide_desktop'])) mm_css_add($ctx, '', $selector, 'display:none');
    if (!empty($s['hide_tablet']))  mm_css_add($ctx, '_tablet', $selector, 'display:none');
    if (!empty($s['hide_mobile']))  mm_css_add($ctx, '_mobile', $selector, 'display:none');
}

/**
 * Element-level custom CSS (the "Custom CSS" advanced field). Elementor
 * substitutes the word "selector" with the element's own selector.
 */
function mm_css_custom(&$ctx, $s, $selector) {
    if (empty($s['custom_css']) || !is_string($s['custom_css'])) return;
    $css = str_replace('selector', $selector, $s['custom_css']);
    $ctx->raw .= "\n" . $css . "\n";
}

/* ------------------------------------------------------------------ */
/* Kit-level defaults (body, headings, links, buttons)                 */
/* ------------------------------------------------------------------ */

function mm_css_kit() {
    $kit = mm_kit_settings();
    $ctx = new stdClass; $ctx->css = array(); $ctx->fonts = array(); $ctx->raw = '';

    // container defaults
    $pad = isset($kit['container_padding']) ? mm_css_box($kit['container_padding']) : '';
    $gap = isset($kit['space_between_widgets']) ? mm_css_dim($kit['space_between_widgets']) : '';
    $cw  = isset($kit['container_width']) ? mm_css_dim($kit['container_width']) : '';
    $root = 'body.mm-rendered';
    mm_css_add($ctx, '', $root, '--mm-container-padding:' . ($pad !== '' ? $pad : '10px'));
    mm_css_add($ctx, '', $root, '--mm-widgets-spacing:' . ($gap !== '' ? $gap : '20px'));
    mm_css_add($ctx, '', $root, '--mm-container-max-width:' . ($cw !== '' ? $cw : '1140px'));
    foreach (array('_tablet', '_mobile') as $d) {
        if (isset($kit['container_padding' . $d])) {
            $p = mm_css_box($kit['container_padding' . $d]);
            if ($p !== '') mm_css_add($ctx, $d, $root, '--mm-container-padding:' . $p);
        }
        if (isset($kit['container_width' . $d])) {
            $w = mm_css_dim($kit['container_width' . $d]);
            if ($w !== '') mm_css_add($ctx, $d, $root, '--mm-container-max-width:' . $w);
        }
    }

    // body text
    mm_css_typography($ctx, $kit, 'body_typography', $root);
    $bc = mm_css_color($kit, 'body_color'); if ($bc !== '') mm_css_add($ctx, '', $root, 'color:' . $bc);
    $bg = mm_css_color($kit, 'body_background_color'); if ($bg !== '') mm_css_add($ctx, '', $root, 'background-color:' . $bg);

    // headings
    foreach (array('h1', 'h2', 'h3', 'h4', 'h5', 'h6') as $h) {
        $sel = "$root .mm-page $h, $root .mm-tpl $h";
        mm_css_typography($ctx, $kit, $h . '_typography', $sel);
        $c = mm_css_color($kit, $h . '_color'); if ($c !== '') mm_css_add($ctx, '', $sel, 'color:' . $c);
    }
    // links
    $lc = mm_css_color($kit, 'link_normal_color'); if ($lc !== '') mm_css_add($ctx, '', "$root .mm-page a, $root .mm-tpl a", 'color:' . $lc);
    $lh = mm_css_color($kit, 'link_hover_color');  if ($lh !== '') mm_css_add($ctx, '', "$root .mm-page a:hover, $root .mm-tpl a:hover", 'color:' . $lh);

    // buttons
    $b = "$root .elementor-button";
    mm_css_typography($ctx, $kit, 'button_typography', $b);
    $c = mm_css_color($kit, 'button_text_color');       if ($c !== '') mm_css_add($ctx, '', $b, 'color:' . $c);
    $c = mm_css_color($kit, 'button_background_color'); if ($c !== '') mm_css_add($ctx, '', $b, 'background-color:' . $c);
    $c = mm_css_color($kit, 'button_hover_text_color'); if ($c !== '') mm_css_add($ctx, '', $b . ':hover', 'color:' . $c);
    $c = mm_css_color($kit, 'button_hover_background_color'); if ($c !== '') mm_css_add($ctx, '', $b . ':hover', 'background-color:' . $c);
    mm_css_border($ctx, $kit, 'button_border', $b);
    mm_css_spacing($ctx, $kit, 'button_padding', 'padding', $b);

    // page-level custom css from the kit
    if (!empty($kit['custom_css'])) $ctx->raw .= "\n" . str_replace('selector', $root, $kit['custom_css']) . "\n";

    return $ctx;
}
