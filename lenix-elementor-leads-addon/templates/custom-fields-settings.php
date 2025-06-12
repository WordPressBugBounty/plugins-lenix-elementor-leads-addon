<?php
if (!defined('ABSPATH')) exit;

$fields = $this->get_fields();
?>

<div class="wrap">
    <h1><?php _e('Custom Lead Fields', 'elementor-leads'); ?></h1>
    
    <div class="custom-fields-container">
        <div class="custom-fields-list">
            <?php foreach ($fields as $field): ?>
                <?php include('custom-field-row.php'); ?>
            <?php endforeach; ?>
        </div>
        
        <button type="button" class="button button-primary add-new-field">
            <?php _e('Add New Field', 'elementor-leads'); ?>
        </button>
        
        <template id="field-row-template">
            <?php
            $field = new stdClass();
            $field->field_key = '';
            $field->field_label = '';
            $field->field_type = 'text';
            $field->default_value = '';
            $field->is_required = 0;
            $field->show_in_list = 0;
            include('custom-field-row.php');
            ?>
        </template>
    </div>
</div> 