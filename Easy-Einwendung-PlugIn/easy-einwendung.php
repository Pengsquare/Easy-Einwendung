<?php
/**
 * Plugin Name: Easy Einwendung 
 * Plugin URI: https://github.com/Pengsquare/Easy-Einwendung
 * Description: Einfache Einwendungen per Textbausteine pflegen und von Nutzenden als E-Mail/PDF Dokument zusammenstellen lassen.
 * Version: 0.4.9
 * Author: Pengsquare UG (haftungsbeschränkt)
 * License: GNUGPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0
 */

if (!defined('ABSPATH')) exit;

/* ------------------------------------------------------------------------ *
 * 1. Load Resources
 * ------------------------------------------------------------------------ */
function som_enqueue_scripts() 
{
    wp_enqueue_script('som-jspdf', plugin_dir_url(__FILE__) . 'assets/js/jspdf.umd.min.js', array(), '2.5.1', true);
}

add_action('wp_enqueue_scripts', 'som_enqueue_scripts');

/* ------------------------------------------------------------------------ *
 * 2. Post Type & Taxonomy
 * ------------------------------------------------------------------------ */
function som_register_core() 
{
    register_post_type('som_mail_block', 
    array(
        'labels' => array('name' => 'Einwendungsbausteine', 'singular_name' => 'Einwendungsbausteine', 'add_new_item' => 'Neuer Baustein', 'edit_item' => 'Baustein bearbeiten'),
        'public' => false, 'show_ui' => true,
        'supports' => array('title', 'editor', 'page-attributes'),
        'menu_icon' => 'dashicons-email-alt',
    ));
    register_taxonomy('som_block_group', 'som_mail_block',
    array(
        'labels' => array('name' => 'Bausteingruppen', 'singular_name' => 'Bausteingruppe', 'menu_name' => 'Bausteingruppen'),
        'hierarchical' => true, 'show_ui' => true, 'show_admin_column' => true, 'query_var' => true,
    ));
}
add_action('init', 'som_register_core');

/* ------------------------------------------------------------------------ *
 * 3. Meta Box (Mandatory and targets)
 * ------------------------------------------------------------------------ */
function som_add_meta_box() { add_meta_box('som_mandatory_section', 'Bausteineinstellungen', 'som_render_meta_box', 'som_mail_block', 'side'); }
add_action('add_meta_boxes', 'som_add_meta_box');

function som_render_meta_box($post) 
{
    $is_mandatory = get_post_meta($post->ID, '_som_is_mandatory', true);
    echo '<p><label><input type="checkbox" name="som_is_mandatory" value="1" ' . checked($is_mandatory, '1', false) . '> <strong>Verpflichtender Baustein</strong></label><br>';
    echo '<span class="description">Versteckt, wird aber in Dokumente übernommen.</span></p>';

    echo '<hr>';

    echo '<p><strong>Verfügbar für diese Empfänger:</strong><br>';
    echo '<span class="description" style="font-size:11px;">Keine Auswahl = Für alle verfügbar.</span></p>';
    
    $targets = get_option('som_targets');
    if (!is_array($targets) || empty($targets)) 
    {
        echo '<p style="color:red">Bitte erst Empfänger in den Einstellungen anlegen.</p>';
    } 
    else 
    {
        $assigned = get_post_meta($post->ID, '_som_assigned_targets', true);
        if(!is_array($assigned)) $assigned = array();

        foreach($targets as $index => $t) 
        {
            $label = !empty($t['label']) ? $t['label'] : 'Empfänger #' . ($index+1);
            $checked = in_array((string)$index, $assigned) ? 'checked' : '';
            echo '<label><input type="checkbox" name="som_assigned_targets[]" value="' . $index . '" ' . $checked . '> ' . esc_html($label) . '</label><br>';
        }
    }
}

function som_save_meta_box($post_id) 
{
    if (array_key_exists('som_is_mandatory', $_POST)) update_post_meta($post_id, '_som_is_mandatory', '1');
    else delete_post_meta($post_id, '_som_is_mandatory');

    if (isset($_POST['som_assigned_targets']) && is_array($_POST['som_assigned_targets']))
    {
        $clean_targets = array_map('sanitize_text_field', $_POST['som_assigned_targets']);
        update_post_meta($post_id, '_som_assigned_targets', $clean_targets);
    } 
    else 
    {
        delete_post_meta($post_id, '_som_assigned_targets');
    }
}
add_action('save_post', 'som_save_meta_box');

/* ------------------------------------------------------------------------ *
 * 4. Settings Page
 * ------------------------------------------------------------------------ */
function som_add_admin_menu() { add_submenu_page('edit.php?post_type=som_mail_block', 'Einwendungen Einstellungen', 'Einstellungen', 'manage_options', 'som-settings', 'som_options_page_html'); }
add_action('admin_menu', 'som_add_admin_menu');

function som_settings_init() 
{ 
    register_setting('som_plugin_options', 'som_targets'); 
    register_setting('som_plugin_options', 'som_cc_email', array('sanitize_callback' => 'sanitize_email'));
    register_setting('som_plugin_options', 'som_intro_text', array('sanitize_callback' => 'sanitize_textarea_field'));
    register_setting('som_plugin_options', 'som_footer_text', array('sanitize_callback' => 'sanitize_textarea_field'));

    register_setting('som_plugin_options', 'som_btn_mail_bg', array('sanitize_callback' => 'sanitize_hex_color'));
    register_setting('som_plugin_options', 'som_btn_pdf_bg', array('sanitize_callback' => 'sanitize_hex_color'));
    register_setting('som_plugin_options', 'som_sel_ind_bg', array('sanitize_callback' => 'sanitize_hex_color'));
    register_setting('som_plugin_options', 'som_btn_mail_col', array('sanitize_callback' => 'sanitize_hex_color'));
    register_setting('som_plugin_options', 'som_btn_pdf_col', array('sanitize_callback' => 'sanitize_hex_color'));
    register_setting('som_plugin_options', 'som_sel_ind_col', array('sanitize_callback' => 'sanitize_hex_color'));
}
add_action('admin_init', 'som_settings_init');

// --- EXPORT HANDLER ---
function som_handle_export() 
{
    if (!current_user_can('manage_options')) return;

    $data = array(
        'settings' => 
        array(
            'som_targets'         => get_option('som_targets'),
            'som_cc_email'        => get_option('som_cc_email'),
            'som_intro_text'      => get_option('som_intro_text'),
            'som_footer_text'     => get_option('som_footer_text'),
            'som_btn_mail_bg'     => get_option('som_btn_mail_bg'),
            'som_btn_pdf_bg'      => get_option('som_btn_pdf_bg'),
            'som_sel_ind_bg'      => get_option('som_sel_ind_bg'),
            'som_btn_mail_col'     => get_option('som_btn_mail_col'),
            'som_btn_pdf_col'      => get_option('som_btn_pdf_col'),
            'som_sel_ind_col'      => get_option('som_sel_ind_col'),
        ),
        'groups' => array(),
        'blocks' => array()
    );

    $terms = get_terms(array('taxonomy' => 'som_block_group', 'hide_empty' => false));
    foreach ($terms as $term) { $data['groups'][] = array('name' => $term->name, 'slug' => $term->slug); }

    $posts = get_posts(array('post_type' => 'som_mail_block', 'posts_per_page' => -1));
    foreach ($posts as $post) 
    {
        $assigned_groups = wp_get_post_terms($post->ID, 'som_block_group', array('fields' => 'names'));
        $assigned_targets = get_post_meta($post->ID, '_som_assigned_targets', true);

        $data['blocks'][] = array(
            'title'     => $post->post_title,
            'content'   => $post->post_content,
            'mandatory' => get_post_meta($post->ID, '_som_is_mandatory', true),
            'targets'   => $assigned_targets,
            'order'     => $post->menu_order,
            'groups'    => $assigned_groups
        );
    }

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="export-einwendung-' . date('Y-m-d') . '.json"');
    echo json_encode($data);
    exit;
}
add_action('admin_post_som_export_json', 'som_handle_export');

// --- IMPORT HANDLER ---
function som_handle_import() 
{
    if (!current_user_can('manage_options')) return;
    if (empty($_FILES['som_import_file']['tmp_name'])) { wp_die('No file uploaded.'); }
    $json = file_get_contents($_FILES['som_import_file']['tmp_name']);
    $data = json_decode($json, true);
    if (!$data || !is_array($data)) { wp_die('Invalid JSON file.'); }

    if (!empty($data['settings'])) { foreach ($data['settings'] as $key => $val) { update_option($key, $val); } }

    if (!empty($data['groups'])) 
    {
        foreach ($data['groups'] as $g) 
        {
            if (!term_exists($g['name'], 'som_block_group')) { wp_insert_term($g['name'], 'som_block_group', array('slug' => $g['slug'])); }
        }
    }

    if (!empty($data['blocks'])) 
    {
        foreach ($data['blocks'] as $b) 
        {
            $existing = get_page_by_title($b['title'], OBJECT, 'som_mail_block');
            $post_data = array('post_title' => $b['title'], 'post_content' => $b['content'], 'post_status' => 'publish', 'post_type' => 'som_mail_block', 'menu_order' => isset($b['order']) ? $b['order'] : 0);
            if ($existing) { $post_data['ID'] = $existing->ID; $post_id = wp_update_post($post_data); } else { $post_id = wp_insert_post($post_data); }
            
            if ($b['mandatory']) update_post_meta($post_id, '_som_is_mandatory', '1'); else delete_post_meta($post_id, '_som_is_mandatory');

            if (isset($b['targets']) && is_array($b['targets'])) update_post_meta($post_id, '_som_assigned_targets', $b['targets']);
            
            if (!empty($b['groups'])) { wp_set_object_terms($post_id, $b['groups'], 'som_block_group'); }
        }
    }
    wp_redirect(admin_url('edit.php?post_type=som_mail_block&page=som-settings&imported=1'));
    exit;
}
add_action('admin_post_som_import_json', 'som_handle_import');

function som_options_page_html() 
{
    if (!current_user_can('manage_options')) return;
    if (isset($_GET['imported'])) echo '<div class="notice notice-success"><p>Daten erfolgreich importiert!</p></div>';
    
    $targets = get_option('som_targets');
    if (!is_array($targets)) $targets = array(); 
    if (empty($targets)) $targets[] = array('label' => '', 'email' => '', 'subject' => '', 'address' => '');
    ?>
    <div class="wrap">
        <h1>Einwendungen - Einstellungen</h1>
        
        <form action="options.php" method="post">
            <?php settings_fields('som_plugin_options'); ?>

            <h3>Empfänger-Profile</h3>
            <p>Hier können Sie mehrere Zieladressen definieren. Der Nutzer wählt im Formular das gewünschte Ziel aus.</p>
            
            <table class="widefat" id="som_targets_table">
                <thead>
                    <tr>
                        <th style="width:20px">ID</th>
                        <th>Bezeichnung (Label für Nutzer)</th>
                        <th>E-Mail Empfänger</th>
                        <th>Betreff</th>
                        <th>Postadresse (für PDF/Mail)</th>
                        <th>Aktion</th>
                    </tr>
                </thead>
                <tbody id="som_targets_body">
                    <?php foreach($targets as $i => $t): ?>
                    <tr class="som-target-row">
                        <td><?php echo $i; ?></td>
                        <td><input type="text" name="som_targets[<?php echo $i; ?>][label]" value="<?php echo esc_attr($t['label']); ?>" class="regular-text" style="width:100%"></td>
                        <td><input type="text" name="som_targets[<?php echo $i; ?>][email]" value="<?php echo esc_attr($t['email']); ?>" class="regular-text" style="width:100%"></td>
                        <td><input type="text" name="som_targets[<?php echo $i; ?>][subject]" value="<?php echo esc_attr($t['subject']); ?>" class="regular-text" style="width:100%"></td>
                        <td><textarea name="som_targets[<?php echo $i; ?>][address]" rows="3" class="large-text" style="width:100%"><?php echo esc_textarea($t['address']); ?></textarea></td>
                        <td><button type="button" class="button som-remove-row">Entfernen</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <br>
            <button type="button" class="button" id="som_add_target"> + Weiteres Ziel hinzufügen</button>

            <hr>

            <h3>Globale Einstellungen</h3>
            <table class="form-table">
                <tr><th scope="row">CC</th><td><input type="text" name="som_cc_email" value="<?php echo esc_attr(get_option('som_cc_email')); ?>" class="regular-text"><p class="description">Optional, wird in E-Mail als CC gesetzt.</p></td></tr>
                <tr><th scope="row">Anschreiben</th><td><textarea name="som_intro_text" rows="4" cols="50" class="large-text"><?php echo esc_textarea(get_option('som_intro_text')); ?></textarea><p class="description">Beginn des Dokumentes/E-Mail.</p></td></tr>
                <tr><th scope="row">Abschlusstext / Footer</th><td><textarea name="som_footer_text" rows="4" cols="50" class="large-text"><?php echo esc_textarea(get_option('som_footer_text')); ?></textarea><p class="description">Letzter Abschnitt im Dokument für Rahmentext und Grußformel.</p></td></tr>
            </table>

            <hr>

            <h3>Design (Farben)</h3>
            <table class="form-table" style="width: 50%">
                <tr>
                    <th scope="row">Farben Auswahlindikator </th>
                    <td>
                        Hintergrund <input type="color" name="som_sel_ind_bg" value="<?php echo esc_attr(get_option('som_sel_ind_bg', '#0073aa')); ?>">
                    </td>
                    <td>
                        Text <input type="color" name="som_sel_ind_col" value="<?php echo esc_attr(get_option('som_sel_ind_col', '#fff')); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Farben Button "E-Mail Entwurf" </th>
                    <td>
                        Hintergrund <input type="color" name="som_btn_mail_bg" value="<?php echo esc_attr(get_option('som_btn_mail_bg', '#0073aa')); ?>">
                    </td>
                    <td>
                        Text <input type="color" name="som_btn_mail_col" value="<?php echo esc_attr(get_option('som_btn_mail_col', '#fff')); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Farben Button "PDF Dokument" </th>
                    <td>
                        Hintergrund <input type="color" name="som_btn_pdf_bg" value="<?php echo esc_attr(get_option('som_btn_pdf_bg', '#d63638')); ?>">
                    </td>
                    <td>
                        Text <input type="color" name="som_btn_pdf_col" value="<?php echo esc_attr(get_option('som_btn_pdf_col', '#fff')); ?>">
                    </td>
                </tr>
            </table>

            <hr>

            <?php submit_button(); ?>
        </form>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var tableBody = document.getElementById('som_targets_body');
            var addButton = document.getElementById('som_add_target');

            addButton.addEventListener('click', function() 
            {
                var rowCount = tableBody.querySelectorAll('tr').length;
                var newRow = document.createElement('tr');
                newRow.className = 'som-target-row';
                newRow.innerHTML = `
                    <td>${rowCount}</td>
                    <td><input type="text" name="som_targets[${rowCount}][label]" class="regular-text" style="width:100%"></td>
                    <td><input type="text" name="som_targets[${rowCount}][email]" class="regular-text" style="width:100%"></td>
                    <td><input type="text" name="som_targets[${rowCount}][subject]" class="regular-text" style="width:100%"></td>
                    <td><textarea name="som_targets[${rowCount}][address]" rows="3" class="large-text" style="width:100%"></textarea></td>
                    <td><button type="button" class="button som-remove-row">Entfernen</button></td>
                `;
                tableBody.appendChild(newRow);
            });

            tableBody.addEventListener('click', function(e) 
            {
                if(e.target && e.target.classList.contains('som-remove-row')) {
                    if(tableBody.querySelectorAll('tr').length > 1) {
                        e.target.closest('tr').remove();
                    } else {
                        alert("Mindestens eine Zeile muss bestehen bleiben.");
                    }
                }
            });
        });
        </script>

        <hr style="margin: 40px 0;">

        <h2>Sichern & Wiederherstellen</h2>
        <div style="display:flex; gap:40px;">
            <div style="background:#fff; padding:20px; border:1px solid #ccd0d4;">
                <h3>Exportiere Daten</h3>
                <p>Laden Sie eine JSON-Datei herunter, die alle Einstellungen, Textbausteine und Gruppen enthält.</p>
                <a href="<?php echo admin_url('admin-post.php?action=som_export_json'); ?>" class="button button-secondary">Exportiere JSON</a>
            </div>
            <div style="background:#fff; padding:20px; border:1px solid #ccd0d4;">
                <h3>Importiere Daten</h3>
                <p>Laden Sie eine JSON-Datei hoch, die alle Einstellungen, Textbausteine und Gruppen enthält.</p>
                <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="som_import_json">
                    <input type="file" name="som_import_file" accept=".json" required>
                    <br><br>
                    <input type="submit" class="button button-secondary" value="Importiere JSON">
                </form>
            </div>
        </div>
    </div>
    <?php
}

/* ------------------------------------------------------------------------ *
 * 5. The Shortcode (Frontend)
 * ------------------------------------------------------------------------ */
function som_block_builder_shortcode($atts) 
{
    $targets = get_option('som_targets');
    if (!is_array($targets) || empty($targets)) 
    {
        $targets = array(array('label' => 'Standard', 'email' => get_option('admin_email'), 'subject' => 'Einwendung', 'address' => ''));
    }

    $global_settings = 
    array(
        'cc'     => get_option('som_cc_email') ?: "", 
        'intro'  => get_option('som_intro_text') ?: "",
        'footer' => get_option('som_footer_text') ?: ""
    );

    // Get Colors with defaults
    $btn_mail_color = get_option('som_btn_mail_bg', '#0073aa');
    $btn_pdf_color = get_option('som_btn_pdf_bg', '#d63638');
    $sel_ind_color = get_option('som_sel_ind_bg', '#0073aa');
    $btn_mail_text_color = get_option('som_btn_mail_col', '#fff');
    $btn_pdf_text_color = get_option('som_btn_pdf_col', '#fff');
    $sel_ind_text_color = get_option('som_sel_ind_col', '#fff');

    // 2. Fetch Blocks
    $all_blocks = get_posts(array('post_type' => 'som_mail_block', 'posts_per_page' => -1, 'orderby' => 'menu_order', 'order' => 'ASC'));
    $mandatory_blocks = []; $selectable_blocks = []; $block_data = []; $block_titles = [];

    foreach ($all_blocks as $block) 
    {
        $block_data[$block->ID] = wpautop($block->post_content);
        $block_titles[$block->ID] = $block->post_title;

        // Pass assigned targets to frontend. If empty, default to 'all' in JS logic logic, or just [] here.
        $assigned = get_post_meta($block->ID, '_som_assigned_targets', true);
        if(!is_array($assigned)) $assigned = null; // null means ALL
        $block->target_ids = $assigned;

        if (get_post_meta($block->ID, '_som_is_mandatory', true)) $mandatory_blocks[] = $block;
        else $selectable_blocks[] = $block;
    }

    ob_start();
    ?>
    
    <style>
        .som-container { max-width: 600px; font-family: sans-serif; }
        .som-details-section { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 25px; }
        .som-details-title { margin-top:0; font-size: 1.1em; border-bottom: 1px solid #ccc; padding-bottom: 8px; margin-bottom: 15px; }
        .som-form-row { display: flex; gap: 15px; margin-bottom: 12px; }
        .som-field { flex: 1; }
        .som-field label { display: block; font-size: 0.9em; font-weight: 600; margin-bottom: 4px; }
        .som-field input, .som-field select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .som-req { color: #d63638; margin-left: 2px; }
        
        .som-target-selection { background: #eef5fa; padding: 15px; border: 1px solid #bce0fd; border-radius: 4px; margin-bottom: 25px; }
        .som-target-label { font-weight: bold; margin-bottom: 10px; display: block; }
        .som-target-option { display: block; margin-bottom: 6px; cursor: pointer; }

        .som-group-wrapper { margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; overflow: hidden; }
        .som-group-header { background: #f1f1f1; padding: 12px 15px; cursor: pointer; font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
        .som-group-header:hover { background: #e9e9e9; }
        .som-group-header.active { border-bottom: 1px solid #ddd; background: #e0e0e0; }
        .som-group-info { display: flex; align-items: center; gap: 10px; }
        .som-counter { background: #0073aa; color: #fff; font-size: 0.75em; padding: 2px 6px; border-radius: 10px; min-width: 25px; text-align: center; }
        .som-group-body { display: none; background: #fff; padding: 10px; }
        
        .som-actions { display: flex; gap: 10px; margin-top: 20px; }
        .som-btn { flex: 1; padding: 12px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; color: #fff; text-align: center; }

        /* Dynamic Colors */
        .som-btn-mail { background: <?php echo esc_attr($btn_mail_color); ?>; }
        .som-btn-pdf { background: <?php echo esc_attr($btn_pdf_color); ?>; }
        .som-counter {background: <?php echo esc_attr($sel_ind_color); ?>;}

        .som-btn-mail { color: <?php echo esc_attr($btn_mail_text_color); ?>; }
        .som-btn-pdf { color: <?php echo esc_attr($btn_pdf_text_color); ?>; }
        .som-counter {color: <?php echo esc_attr($sel_ind_text_color); ?>;}

        .som-btn:hover { opacity: 0.9; }

        .som-block-row { margin-bottom: 8px; border: 1px solid #eee; border-radius: 4px; }
        .som-block-header { padding: 8px 12px; display: flex; align-items: center; justify-content: space-between; background: #fff; }
        .som-block-header label { font-weight: 500; cursor: pointer; flex-grow: 1; margin-left: 8px; }
        .som-block-content { display: none; padding: 10px 15px; border-top: 1px dashed #eee; font-size: 0.9em; color: #555; background: #fafafa;}
        .som-text-toggle { font-size: 11px; color: #0073aa; cursor: pointer; border: none; background: none; text-decoration: underline; }
    </style>

    <div class="som-container">
        <form id="som-block-form">
            <div class="som-details-section">
                <div class="som-form-row"><div class="som-field"><label>Vorname <span class="som-req">*</span></label><input type="text" id="som_fname"></div><div class="som-field"><label>Nachname <span class="som-req">*</span></label><input type="text" id="som_lname"></div></div>
                <div class="som-form-row"><div class="som-field" style="flex:2;"><label>Straße/Hausnummer <span class="som-req">*</span></label><input type="text" id="som_street"></div></div>
                <div class="som-form-row"><div class="som-field"><label>PLZ <span class="som-req">*</span></label><input type="text" id="som_zip"></div><div class="som-field" style="flex:2;"><label>Ort <span class="som-req">*</span></label><input type="text" id="som_city"></div></div>
                <div class="som-form-row"><div class="som-field"><label>E-Mail <span class="som-req">*</span></label><input type="email" id="som_email"></div></div>
            </div>

            <?php 
                // Hide if only 1 target
                $target_count = count($targets);
                $hide_target_style = ($target_count <= 1) ? 'style="display:none;"' : '';
            ?>
        
            <div class="som-target-selection" <?php echo $hide_target_style; ?>>
                <label class="som-target-label">An wen soll sich die Einwendung richten?<span class="som-req">*</span></label>
                <?php foreach($targets as $index => $target): ?>
                    <label class="som-target-option">
                        <input type="radio" name="som_target_radio" value="<?php echo $index; ?>" <?php checked($index, 0); ?>> 
                        <?php echo esc_html($target['label']); ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <h3>Themenauswahl zur Einwendung</h3>
            <?php foreach ($mandatory_blocks as $block): ?><input type="checkbox" name="som_blocks[]" value="<?php echo $block->ID; ?>" checked style="display:none;"><?php endforeach; ?>

            <?php 
            foreach ($mandatory_blocks as $block) { som_render_row($block, true); }

            $groups = get_terms(array('taxonomy' => 'som_block_group', 'hide_empty' => false));
            $printed_ids = array(); 
            $group_count = 0;
            if (!empty($groups) && !is_wp_error($groups)) 
            {
                foreach ($groups as $group) 
                {
                    $group_blocks = array_filter($selectable_blocks, function($b) use ($group) { return has_term($group->term_id, 'som_block_group', $b->ID); });
                    if (!empty($group_blocks)) 
                    {
                        $group_count++; $is_open_style = ($group_count === 1) ? 'display:block;' : 'display:none;'; $active_class = ($group_count === 1) ? 'active' : ''; $total_items = count($group_blocks);
                        echo '<div class="som-group-wrapper"><div class="som-group-header ' . $active_class . '"><span>' . esc_html($group->name) . '</span><div class="som-group-info"><span class="som-counter" data-total="' . $total_items . '">0/' . $total_items . '</span><span>▼</span></div></div><div class="som-group-body" style="' . $is_open_style . '">';
                        foreach ($group_blocks as $block) { som_render_row($block); $printed_ids[] = $block->ID; }
                        echo '</div></div>';
                    }
                }
            }
            $orphan_blocks = array_filter($selectable_blocks, function($b) use ($printed_ids) { return !in_array($b->ID, $printed_ids); });
            if (!empty($orphan_blocks)) { $total_items = count($orphan_blocks); echo '<div class="som-group-wrapper"><div class="som-group-header"><span>Other</span><div class="som-group-info"><span class="som-counter" data-total="' . $total_items . '">0/' . $total_items . '</span><span>▼</span></div></div><div class="som-group-body" style="display:none;">'; foreach ($orphan_blocks as $block) { som_render_row($block); } echo '</div></div>'; }
            ?>

            <div class="som-actions">
                <button type="button" id="som-draft-btn" class="som-btn som-btn-mail">Einwendung als E-Mail Entwurf</button>
                <button type="button" id="som-pdf-btn" class="som-btn som-btn-pdf">Einwendung als PDF Dokument</button>
            </div>
            <div style="font-size: 12px; margin-top: 20px">
                Hinweis: Daten werden nur im Browser verarbeitet, es werden keine Informationen zentral prozessiert oder gespeichert.
            </div>
        </form>

        <script type="text/javascript">
            var somBlockData = <?php echo json_encode($block_data); ?>;
            var somBlockTitles = <?php echo json_encode($block_titles); ?>;
            var somTargets = <?php echo json_encode($targets); ?>;
            var somGlobal = <?php echo json_encode($global_settings); ?>;

            function filterBlocksByTarget(targetIndex) 
            {
                targetIndex = String(targetIndex);

                var rows = document.querySelectorAll('.som-block-row');
                rows.forEach(function(row) {
                    var rawTargets = row.getAttribute('data-targets');
                    var show = false;
                    
                    if(rawTargets === 'all') 
                    {
                        show = true;
                    }
                    else 
                    {
                        try 
                        {
                            var allowed = JSON.parse(rawTargets);
                            if(allowed.includes(targetIndex)) show = true;
                        } catch(e) { show = true; } // fallback
                    }

                    if(show) 
                    {
                        row.style.display = 'block';
                        var cb = row.querySelector('input[type="checkbox"]');
                        if(cb) cb.disabled = false;
                    } 
                    else 
                    {
                        row.style.display = 'none';
                        var cb = row.querySelector('input[type="checkbox"]');
                        if(cb) 
                        {
                            cb.checked = false; // Uncheck hidden blocks
                            cb.disabled = true; // Disable to exclude from POST/Logic
                        }
                    }
                });

                var mandatoryWrappers = document.querySelectorAll('.som-mandatory-wrapper');
                mandatoryWrappers.forEach(function(wrap) 
                {
                    var rawTargets = wrap.getAttribute('data-targets');
                    var active = false;
                    
                    if(rawTargets === 'all') 
                    {
                        active = true;
                    }
                    else 
                    {
                        try 
                        {
                            var allowed = JSON.parse(rawTargets);
                            if(allowed.includes(targetIndex)) active = true;
                        } catch(e) { active = true; }
                    }

                    var inp = wrap.querySelector('input');
                    if(active) 
                    {
                        if(inp) { inp.disabled = false; inp.checked = true; }
                    } 
                    else 
                    {
                        if(inp) { inp.disabled = true; inp.checked = false; }
                    }
                });

                document.querySelectorAll('.som-group-wrapper').forEach(function(g)
                {
                    var totalVisible = g.querySelectorAll('.som-block-row:not([style*="display: none"]) input[type="checkbox"]').length;
                    var checkedVisible = g.querySelectorAll('.som-block-row:not([style*="display: none"]) input[type="checkbox"]:checked').length;
                    
                    var counter = g.querySelector('.som-counter');
                    if(counter) 
                    {
                        counter.innerText = checkedVisible + '/' + totalVisible;
                        
                        if(totalVisible === 0) g.style.display = 'none';
                        else g.style.display = 'block';
                    }
                });
            }

            var targetRadios = document.querySelectorAll('input[name="som_target_radio"]');
            targetRadios.forEach(function(r) 
            {
                r.addEventListener('change', function() 
                {
                    filterBlocksByTarget(this.value);
                });
            });

            var checkedRadio = document.querySelector('input[name="som_target_radio"]:checked');
            if(checkedRadio) 
            {
                filterBlocksByTarget(checkedRadio.value);
            } 
            else if (targetRadios.length > 0)
            {
                targetRadios[0].checked = true;
                filterBlocksByTarget(targetRadios[0].value);
            }

            function toggleDisplay(el) { el.style.display = (el.style.display === 'block') ? 'none' : 'block'; }
            document.querySelectorAll('.som-group-header').forEach(h => h.addEventListener('click', function(e) { if(e.target.classList.contains('som-counter')) return; this.classList.toggle('active'); toggleDisplay(this.nextElementSibling); }));
            document.querySelectorAll('.som-text-toggle').forEach(b => b.addEventListener('click', function(e) { e.preventDefault(); var d = document.getElementById(this.getAttribute('data-target')); if (d.style.display === 'block') { d.style.display = 'none'; this.innerText = 'Details'; } else { d.style.display = 'block'; this.innerText = 'Weniger'; } }));
            
            document.querySelectorAll('input[name="som_blocks[]"]').forEach(box => box.addEventListener('change', function() 
            { 
                var w = this.closest('.som-group-wrapper'); 
                if(w) 
                { 
                    var totalVisible = w.querySelectorAll('.som-block-row:not([style*="display: none"]) input[type="checkbox"]').length;
                    var checkedVisible = w.querySelectorAll('.som-block-row:not([style*="display: none"]) input[type="checkbox"]:checked').length;
                    w.querySelector('.som-counter').innerText = checkedVisible + '/' + totalVisible; 
                } 
            }));

            function getFormData() 
            {
                var d = { fname: document.getElementById('som_fname').value.trim(), lname: document.getElementById('som_lname').value.trim(), street: document.getElementById('som_street').value.trim(), zip: document.getElementById('som_zip').value.trim(), city: document.getElementById('som_city').value.trim(), email: document.getElementById('som_email').value.trim(), checks: document.querySelectorAll('input[name="som_blocks[]"]:checked:not([disabled])') };
                if(!d.fname || !d.lname || !d.street || !d.zip || !d.city || !d.email) { alert("Bitte füllen Sie alle mit * markierten Felder aus."); return null; }
                if(d.checks.length === 0 || d.checks.length < 2) { alert("Bitte wählen sie wenigstens 2 Begründungen aus."); return null; }
                return d;
            }
            function stripHtml(html) { var div = document.createElement("div"); div.innerHTML = html; return div.innerText || div.textContent || ""; }

            document.getElementById('som-draft-btn').addEventListener('click', function() 
            {
                var d = getFormData(); if(!d) return;

                var radios = document.getElementsByName('som_target_radio');
                var targetIdx = 0;
                for (var i = 0; i < radios.length; i++) { if (radios[i].checked) { targetIdx = radios[i].value; break; } }
                var currentTarget = somTargets[targetIdx];

                var ampersand = String.fromCharCode(38);
                var address = currentTarget.address ? (currentTarget.address + "\r\n\r\n") : "";
                var subjectLine = currentTarget.subject ? (currentTarget.subject) : "Einwendung";
                var subjectBody = "Betreff: " + subjectLine + "\r\n\r\n";
                
                var intro = somGlobal.intro ? (somGlobal.intro + "\r\n\r\n") : "";
                var footer = somGlobal.footer ? ("\r\n\r\n" + somGlobal.footer) : "";
                var signature = "\r\n\r\n" + d.fname + " " + d.lname; 
                var details = "\r\n\r\n\r\n--------------------------------------------------\r\nKontakt:\r\n" + d.email + "\r\n" + d.street + ",\r\n" + d.zip + " " + d.city + "\r\n";
                
                var bodyParts = [];
                d.checks.forEach(function(c) { bodyParts.push(stripHtml(somBlockData[c.value]).trim()); });
                var finalBody = address + subjectBody + intro + bodyParts.join("\r\n\r\n") + footer + signature + details;
                var ccParam = somGlobal.cc ? (ampersand + "cc=" + somGlobal.cc) : "";
                
                window.location.href = "mailto:" + currentTarget.email + "?subject=" + encodeURIComponent(subjectLine) + ccParam + ampersand + "body=" + encodeURIComponent(finalBody);
            });

            document.getElementById('som-pdf-btn').addEventListener('click', function() 
            {
                var d = getFormData(); if(!d) return;
                if(!window.jspdf) { alert("PDF Library not loaded yet."); return; }
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();

                var radios = document.getElementsByName('som_target_radio');
                var targetIdx = 0;
                for (var i = 0; i < radios.length; i++) { if (radios[i].checked) { targetIdx = radios[i].value; break; } }
                var currentTarget = somTargets[targetIdx];
                
                var cursorY = 20; var margin = 15; var pageWidth = 210; var maxLineWidth = pageWidth - (margin * 2);

                doc.setFontSize(12); doc.setFont("helvetica", "normal");
                if(currentTarget.address) 
                {
                    var companyLines = doc.splitTextToSize(currentTarget.address, 90); doc.text(companyLines, margin, cursorY);
                } 
                else 
                {
                    var companyLines = [];
                }
                
                doc.setFont("helvetica", "normal");
                var details = [d.fname + " " + d.lname, d.street, d.zip + " " + d.city, d.email, "\n" + new Date().toLocaleDateString()];
                var rightX = pageWidth - margin; var detailsY = 20; // reset Y to top for user details
                details.forEach(function(line) { doc.text(line, rightX, detailsY, { align: "right" }); detailsY += 5; });
                
                cursorY = Math.max(20 + (companyLines.length * 5), detailsY) + 5;

                cursorY = cursorY+40;
                if(currentTarget.subject) 
                {
                    doc.setFont("helvetica", "bold");
                    var subjLines = doc.splitTextToSize("Betreff: " + currentTarget.subject, maxLineWidth); doc.text(subjLines, margin, cursorY); cursorY += (subjLines.length * 5) + 10;
                    cursorY = cursorY + 10;
                }

                if(somGlobal.intro) 
                {
                    doc.setFont("helvetica", "normal");
                    var introLines = doc.splitTextToSize(somGlobal.intro, maxLineWidth); doc.text(introLines, margin, cursorY); cursorY += (introLines.length * 5) + 5;
                }

                doc.setFontSize(12);
                d.checks.forEach(function(c) 
                {
                    var content = stripHtml(somBlockData[c.value]).trim();
                    if (cursorY > 270) { doc.addPage(); cursorY = 20; }
                    doc.setFont("helvetica", "normal");
                    var lines = doc.splitTextToSize(content, maxLineWidth);
                    if (cursorY + (lines.length * 6) > 280) { doc.addPage(); cursorY = 20; }
                    doc.text(lines, margin, cursorY); cursorY += (lines.length * 6); 
                });


                if (cursorY > 260) { doc.addPage(); cursorY = 20; }
                if(somGlobal.footer) 
                {
                    var footerLines = doc.splitTextToSize(somGlobal.footer, maxLineWidth); doc.text(footerLines, margin, cursorY); cursorY += (footerLines.length * 5) + 8;
                }
                if (cursorY > 280) { doc.addPage(); cursorY = 20; }
                doc.setFont("helvetica", "normal"); doc.setFontSize(12); doc.setTextColor(0);
                doc.text(d.fname + " " + d.lname, margin, cursorY);

                doc.save("Einwendung_" + d.lname + ".pdf");
            });
        </script>
    </div>
    <?php
    return ob_get_clean();
}

function som_render_row($block, $is_mandatory = false) 
{
    $content_id = 'som-content-' . $block->ID;
    
    $targets_attr = ($block->target_ids === null) ? 'all' : json_encode($block->target_ids);
    
    if ($is_mandatory) 
    {
        echo '<span class="som-mandatory-wrapper" data-targets="' . esc_attr($targets_attr) . '">';
        echo '<input type="checkbox" name="som_blocks[]" value="' . $block->ID . '" checked style="display:none;">';
        echo '</span>';
    } 
    else 
    {
        echo '<div class="som-block-row" data-targets="' . esc_attr($targets_attr) . '">';
        echo '<div class="som-block-header">';
        echo '<input type="checkbox" name="som_blocks[]" value="' . $block->ID . '">'; 
        echo '<label>' . esc_html($block->post_title) . '</label>';
        echo '<button class="som-text-toggle" data-target="' . $content_id . '">Details</button>';
        echo '</div>';
        echo '<div id="' . $content_id . '" class="som-block-content">' . wpautop($block->post_content) . '</div>';
        echo '</div>';
    }
}

add_shortcode('einwendung_baukasten', 'som_block_builder_shortcode');