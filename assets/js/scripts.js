jQuery(document).ready(function($) {
    // All schemas JSON code box "code_box"
    $('#code_box').ready(function(){
        var codeBoxEditor = wp.codeEditor.initialize($('#code_box'), {
            codemirror: {
                lineNumbers: true,
                mode: 'application/json',
                readOnly: true
            }
        }).codemirror;
        var numberOfRows = 35;
        var lineHeight = 20;
        codeBoxEditor.setSize(null, numberOfRows * lineHeight + "px");
        // Json schema preview functionality
        $('.preview-schema').on('click', function(e) {
            e.preventDefault();
            var schemaJSON = $(this).data('schema');
            if (typeof schemaJSON === 'string') {
                try {
                    var parsedJSON = JSON.parse(schemaJSON);
                    var formattedJSON = JSON.stringify(parsedJSON, null, 2);
                    codeBoxEditor.setValue(formattedJSON);
                } catch (e) {
                    console.error('Error parsing JSON:', e);
                    codeBoxEditor.setValue(schemaJSON);
                }
            } else {
                codeBoxEditor.setValue(JSON.stringify(schemaJSON, null, 2));
            }
        });
    });
    // Add/Edit schema JSON code box "schemaJson"
    $('#schemaJson').ready(function(){
        var schemaJsonEditor = wp.codeEditor.initialize($('#schemaJson'), {
            codemirror: {
                lineNumbers: true,
                mode: 'application/json',
                readOnly: false
            }
        }).codemirror;
        var numberOfRows = 22;
        var lineHeight = 20;
        schemaJsonEditor.setSize(null, numberOfRows * lineHeight + "px");
    });
    // Enable or disable box based on dependent select box
    $('#postType').on('change', function() {
        var postType = $(this).val();
        $('#postID').prop('disabled', true).html('<option value="">Loading...</option>');
        $('#schemaType').prop('disabled', true);
        $('#schemaJson').prop('disabled', true);
        if (postType) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_posts_by_type',
                    post_type: postType
                },
                success: function(response) {
                    var options = '<option value="">Select target</option>';
                    if (response.success && response.data.length > 0) {
                        if (postType == 'page') {
                            options += '<option value="pages">All Pages</option>';
                        }
                        $.each(response.data, function(index, post) {
                            options += '<option value="' + post.ID + '">' + post.post_title + '</option>';
                        });
                    } else {
                        options = '<option value="">Nothing found</option>';
                    }
                    $('#postID').html(options).prop('disabled', false);
                },
                error: function() {
                    $('#postID').html('<option value="">Error loading</option>').prop('disabled', false);
                }
            });
        } else {
            $('#postID').html('<option value="">Select type first</option>').prop('disabled', true);
        }
    });
    // Enable or disable box based on dependent select box
    $('#postID').on('change', function() {
        var postID = $(this).val();
        $('#schemaType').prop('disabled', true);
        $('#schemaJson').prop('disabled', true);
        if (postID != '') {
            $('#schemaType').prop('disabled', false);
        } else {
            $('#schemaType').prop('disabled', true);
        }
    });
    // Enable or disable box based on dependent select box
    $('#schemaType').on('change', function() {
        var schemaType = $(this).val();
        $('#schemaJson').prop('disabled', true);
        if (schemaType != '') {
            $('#schemaJson').prop('disabled', false);
        } else {
            $('#schemaJson').prop('disabled', true);
        }
    });
});