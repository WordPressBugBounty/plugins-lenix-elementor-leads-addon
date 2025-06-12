<?php if (!defined('ABSPATH')) exit; 

// Determine if this is a template or existing field
$is_template = !isset($field);
$field = $is_template ? (object)[
    'field_key' => '',
    'field_label' => '',
    'field_type' => 'text',
    'default_value' => '',
    'is_required' => false
] : (object)array_merge([
    'field_key' => '',
    'field_label' => '',
    'field_type' => 'text',
    'default_value' => '',
    'is_required' => false
], (array)$field);

// For JavaScript template, we need to escape the template properly
if ($is_template) {
    ob_start();
}
?>

<div class="custom-field-row" <?php echo $is_template ? '' : 'data-id="' . esc_attr($field->field_key) . '"'; ?> <?php echo $is_template ? 'style="display: none;"' : ''; ?>>
    <div class="field-header">
        <span class="field-drag-handle dashicons dashicons-menu"></span>
        <span class="field-title"><?php echo esc_html($field->field_label ?: __('New Field', 'elementor-leads')); ?></span>
        <div class="field-actions">
            <button type="button" class="button edit-field"><?php _e('Edit', 'elementor-leads'); ?></button>
            <button type="button" class="button delete-field"><?php _e('Delete', 'elementor-leads'); ?></button>
        </div>
    </div>
    
    <div class="field-content" <?php echo !$is_template ? 'style="display: none;"' : ''; ?>>
        <div class="field-row">
            <label>
                <?php _e('Field Label', 'elementor-leads'); ?>
                <input type="text" name="field_label" value="<?php echo esc_attr($field->field_label); ?>" required>
            </label>
        </div>

        <div class="field-row">
            <label>
                <?php _e('Field Key', 'elementor-leads'); ?>
                <input type="text" name="field_key" value="<?php echo esc_attr($field->field_key); ?>" readonly>
            </label>
            <p class="description"><?php _e('Field key is automatically generated from the field label.', 'elementor-leads'); ?></p>
        </div>

        <div class="field-row">
            <label>
                <?php _e('Field Type', 'elementor-leads'); ?>
                <select name="field_type">
                    <option value="text" <?php selected($field->field_type, 'text'); ?>><?php _e('Text', 'elementor-leads'); ?></option>
                    <option value="email" <?php selected($field->field_type, 'email'); ?>><?php _e('Email', 'elementor-leads'); ?></option>
                    <option value="number" <?php selected($field->field_type, 'number'); ?>><?php _e('Number', 'elementor-leads'); ?></option>
                    <option value="textarea" <?php selected($field->field_type, 'textarea'); ?>><?php _e('Textarea', 'elementor-leads'); ?></option>
                </select>
            </label>
        </div>

        <div class="field-row">
            <label>
                <?php _e('Default Value', 'elementor-leads'); ?>
                <input type="text" name="default_value" value="<?php echo esc_attr($field->default_value); ?>">
            </label>
        </div>

        <div class="field-row">
            <label>
                <input type="checkbox" name="is_required" <?php checked($field->is_required); ?>>
                <?php _e('Required Field', 'elementor-leads'); ?>
            </label>
        </div>

        <div class="field-actions">
            <button type="button" class="button button-primary save-field"><?php _e('Save Field', 'elementor-leads'); ?></button>
            <button type="button" class="button cancel-edit"><?php _e('Cancel', 'elementor-leads'); ?></button>
        </div>
    </div>
</div>

<?php
if ($is_template) {
    $template = ob_get_clean();
    // Instead of using script tag, we'll just output the template directly
    echo $template;
}
?> 