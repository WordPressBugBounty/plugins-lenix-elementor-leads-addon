<?php
if (!defined('ABSPATH')) exit;

$fields = $this->get_fields();
?>

<div class="wrap">
    <h1><?php _e('Custom Lead Fields', 'elementor-leads'); ?></h1>
    
    <div class="custom-fields-container">
        <div class="custom-fields-list">
            <?php foreach ($fields as $field): ?>
                <?php include(ELEMENTOR_LEADS_PATH . 'templates/custom-fields/field-row.php'); ?>
            <?php endforeach; ?>
        </div>
        
        <button type="button" class="button button-primary add-new-field">
            <?php _e('Add New Field', 'elementor-leads'); ?>
        </button>
    </div>
</div>

<!-- Template for new fields -->
<div id="field-row-template" style="display: none;">
    <div class="custom-field-row">
        <div class="field-header">
            <span class="field-drag-handle dashicons dashicons-menu"></span>
            <span class="field-title"><?php _e('New Field', 'elementor-leads'); ?></span>
            <div class="field-actions">
                <button type="button" class="button edit-field"><?php _e('Edit', 'elementor-leads'); ?></button>
                <button type="button" class="button delete-field"><?php _e('Delete', 'elementor-leads'); ?></button>
            </div>
        </div>
        
        <div class="field-content">
            <div class="field-row">
                <label>
                    <?php _e('Field Label', 'elementor-leads'); ?>
                    <input type="text" name="field_label" value="" required>
                </label>
            </div>

            <div class="field-row" style="display: none;">
                <label>
                    <?php _e('Field Key', 'elementor-leads'); ?>
                    <input type="text" name="field_key" value="" readonly>
                </label>
                <p class="description"><?php _e('Field key is automatically generated.', 'elementor-leads'); ?></p>
            </div>

            <div class="field-row">
                <label>
                    <?php _e('Field Type', 'elementor-leads'); ?>
                    <select name="field_type">
                        <option value="text"><?php _e('Text', 'elementor-leads'); ?></option>
                        <option value="email"><?php _e('Email', 'elementor-leads'); ?></option>
                        <option value="number"><?php _e('Number', 'elementor-leads'); ?></option>
                        <option value="textarea"><?php _e('Textarea', 'elementor-leads'); ?></option>
                    </select>
                </label>
            </div>

            <div class="field-row">
                <label>
                    <?php _e('Default Value', 'elementor-leads'); ?>
                    <input type="text" name="default_value" value="">
                </label>
            </div>

            <div class="field-row">
                <label>
                    <input type="checkbox" name="is_required">
                    <?php _e('Required Field', 'elementor-leads'); ?>
                </label>
            </div>

            <div class="field-actions">
                <button type="button" class="button button-primary save-field"><?php _e('Save Field', 'elementor-leads'); ?></button>
                <button type="button" class="button cancel-edit"><?php _e('Cancel', 'elementor-leads'); ?></button>
            </div>
        </div>
    </div>
</div> 