<?php
if (!defined('ABSPATH')) exit;

// Check edit permissions first
$edit_cap = get_option('elementor_leads_edit_role', 'edit_others_posts');
$can_edit = current_user_can($edit_cap);

$fields = $this->get_fields();
$values = $this->get_lead_values($post->ID);
?>

<div class="custom-fields-meta-box">
    <?php if (!$can_edit): ?>
        <div class="notice notice-error inline">
            <p><?php _e('You do not have permission to edit leads. Please contact your administrator for full access.', 'elementor-leads'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (empty($fields)): ?>
        <p><?php _e('No custom fields defined yet.', 'elementor-leads'); ?></p>
    <?php else: ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php _e('Field', 'elementor-leads'); ?></th>
                    <th><?php _e('Value', 'elementor-leads'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fields as $field): ?>
                    <tr>
                        <td>
                            <label for="custom_field_<?php echo esc_attr($field->field_key); ?>">
                                <?php echo esc_html($field->field_label); ?>
                                <?php if ($field->is_required == 1): ?>
                                    <span class="required">*</span>
                                <?php endif; ?>
                            </label>
                        </td>
                        <td>
                            <?php
                            $value = isset($values[$field->field_key]) ? $values[$field->field_key] : $field->default_value;
                            
                            if ($can_edit) {
                                // Show editable fields
                                $this->render_field_input($field, $value);
                            } else {
                                // Show read-only value
                                echo esc_html($value);
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.custom-fields-meta-box table {
    width: 100%;
    border-collapse: collapse;
}

.custom-fields-meta-box th,
.custom-fields-meta-box td {
    padding: 8px;
    text-align: left;
    border-bottom: 1px solid #e2e4e7;
}

.custom-fields-meta-box label {
    font-weight: 600;
}

.custom-fields-meta-box .required {
    color: #dc3232;
    margin-left: 3px;
}

.custom-fields-meta-box input[type="text"],
.custom-fields-meta-box input[type="email"],
.custom-fields-meta-box input[type="number"],
.custom-fields-meta-box textarea,
.custom-fields-meta-box select {
    width: 100%;
    max-width: 400px;
}

.custom-fields-meta-box textarea {
    min-height: 100px;
}
</style>