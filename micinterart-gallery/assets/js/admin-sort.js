jQuery(document).ready(function($) {
    var $list = $("#mic-gallery-sortable");

    $list.sortable({
        placeholder: "ui-state-highlight"
    });

    $("#mic-gallery-save").on("click", function() {
        var order = $list.sortable("toArray");

        $.post(micGalleryAjax.ajax_url, {
            action: "mic_gallery_save_order",
            order: order,
            _ajax_nonce: micGalleryAjax.nonce
        }, function(response) {
            if (response.success) {
                $("#mic-gallery-message").html('<div style="color:green;">' + response.data + '</div>');
            } else {
                $("#mic-gallery-message").html('<div style="color:red;">Fehler: ' + response.data + '</div>');
            }
        });
    });
});