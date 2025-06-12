jQuery(document).ready(function($) {
    const container = $('.custom-fields-container');
    const fieldsList = $('.custom-fields-list');
    const template = $('#field-row-template');
    
    // Function to generate field key from label
    function generateFieldKey(label) {
        return 'field_' + label
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .substring(0, 30);
    }

    // Add new field
    $('.add-new-field').on('click', function() {
        const newRow = template.find('.custom-field-row').clone();
        fieldsList.append(newRow);
        newRow.find('.field-content').show();
        newRow.find('.field-header').hide();
        
        // Auto-generate field key when label changes
        newRow.find('[name="field_label"]').on('input', function() {
            const label = $(this).val();
            if (label) {
                newRow.find('[name="field_key"]').val(generateFieldKey(label));
            }
        });
    });

    // Edit field
    fieldsList.on('click', '.edit-field', function() {
        const row = $(this).closest('.custom-field-row');
        row.find('.field-content').show();
        row.find('.field-header').hide();
    });

    // Save field
    fieldsList.on('click', '.save-field', function() {
        const row = $(this).closest('.custom-field-row');
        const fieldLabel = row.find('[name="field_label"]').val();
        
        if (!fieldLabel) {
            alert(elementorLeadsVars.messages.required_fields);
            return;
        }

        // Generate field key if empty
        const fieldKey = row.find('[name="field_key"]').val() || generateFieldKey(fieldLabel);
        row.find('[name="field_key"]').val(fieldKey);

        const data = {
            action: 'save_custom_field',
            nonce: elementorLeadsVars.nonce,
            field_key: fieldKey,
            field_label: fieldLabel,
            field_type: row.find('[name="field_type"]').val(),
            default_value: row.find('[name="default_value"]').val(),
            is_required: row.find('[name="is_required"]').is(':checked') ? '1' : '',
            field_order: row.index()
        };

        const $button = $(this);
        $button.prop('disabled', true).text(elementorLeadsVars.messages.saving);

        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                row.find('.field-title').text(fieldLabel);
                row.find('.field-content').hide();
                row.find('.field-header').show();
            } else {
                alert(response.data);
            }
        }).fail(function() {
            alert(elementorLeadsVars.messages.error);
        }).always(function() {
            $button.prop('disabled', false).text(elementorLeadsVars.messages.save_field);
        });
    });

    // Cancel edit
    fieldsList.on('click', '.cancel-edit', function() {
        const row = $(this).closest('.custom-field-row');
        if (!row.data('id')) {
            row.remove();
        } else {
            row.find('.field-content').hide();
            row.find('.field-header').show();
        }
    });

    // Delete field
    fieldsList.on('click', '.delete-field', function() {
        if (!confirm(elementorLeadsVars.messages.confirm_delete)) {
            return;
        }

        const row = $(this).closest('.custom-field-row');
        const fieldKey = row.data('id');

        if (!fieldKey) {
            row.remove();
            return;
        }

        const data = {
            action: 'delete_custom_field',
            nonce: elementorLeadsVars.nonce,
            field_key: fieldKey
        };

        const $button = $(this);
        $button.prop('disabled', true);

        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                row.remove();
            } else {
                alert(response.data);
            }
        }).fail(function() {
            alert(elementorLeadsVars.messages.error);
        }).always(function() {
            $button.prop('disabled', false);
        });
    });

    // Make fields sortable
    fieldsList.sortable({
        handle: '.field-drag-handle',
        update: function(event, ui) {
            const data = {
                action: 'update_fields_order',
                nonce: elementorLeadsVars.nonce,
                order: fieldsList.find('.custom-field-row').map(function() {
                    return $(this).data('id');
                }).get()
            };

            $.post(ajaxurl, data);
        }
    });
}); 