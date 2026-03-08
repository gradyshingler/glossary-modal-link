(function ($, Drupal) {

    Drupal.behaviors.glossaryModalLink = {
        attach: function (context, settings) {
            $('.glossary-modal-link', context).once('glossary-modal-trigger')
                .on('click', function (e) {
                    e.preventDefault();
                    // TODO: Figure out a way to get the target whether it is a data-bs-target (boostrap 5) or data-target (bootstrap 4)
                    // let targetModal = $(this).data('bs-target');
                    let targetModal = $(this).data('target');

                    console.log("targetModal: ", targetModal);

                    let title = $(this).data('modal-title');
                    let modalSize = $(this).data('modal-size');
                    let buttonLink = $(this).data('button-link');
                    let buttonLabel = $(this).data('button-label');
                    let disabledStatus = $(this).data('disabled-status');
                    let url = $(this).data('ajax-url');
                    console.log("title: ", title);
                    console.log("modalSize: ", modalSize);
                    console.log("buttonLink: ", buttonLink);
                    console.log("buttonLabel: ", buttonLabel);
                    console.log("disabledStatus: ", disabledStatus);
                    console.log("url: ", url);

                    let modal = $(targetModal);

                    // Load the spinner to show until the content is loaded
                    modal.find('.modal-body').html('<div class="text-center p-4"><span class="spinner-border"></span></div>');

                    // Set the various data points for the modal
                    // Set the title
                    if (title) {
                        $('#glossaryModal .modal-title').text(title);
                    }

                    if(modalSize) {
                        // Optionally adjust modal classes if needed
                        modal.find('.modal-dialog').removeClass('modal-xs modal-sm modal-md modal-lg modal-xl').addClass('modal-' + modalSize);
                    }
                    if(buttonLink) {
                        modal.find('.action-button').html(buttonLabel);
                        modal.find('.action-button').attr('href', buttonLink);
                        // Set the disabled status
                        modal.find('.action-button').removeClass("disabled");
                        if(disabledStatus === "disabled") {
                            modal.find('.action-button').addClass("disabled");
                        }
                        modal.find('.action-button').show();
                    }
                    if(buttonLabel === "") {
                        modal.find('.action-button').hide();
                    }

                    console.log("pre show");
                    // $('#glossaryModal').modal('show');

                    console.log("showing Modal and starting ajax call");

                    $.get(url, function (data) {
                        // Grab any metadata content from the node to update the modal
                        console.log("Inside Get");
                        if (data) {
                            let $data = $('<div>').html(data);
                            $('#glossaryModal .modal-body').html($data);
                        } else {
                            $('#glossaryModal .modal-body').html('<div class="alert alert-danger">Could not load content.</div>');
                        }
                    }).fail(function () {
                        $('#glossaryModal .modal-body').html('<div class="alert alert-danger">Failed to retrieve content. Please try again.</div>');
                    });
                });

            $('#glossaryModal', context).once('glossary-modal-hidden')
                .on('hidden.bs.modal', function () {
                    // Load the spinner to show until the content is loaded
                    $('#glossaryModal .modal-body').html('<div class="text-center p-4"><span class="spinner-border"></span></div>');
                    $('#glossaryModal .action-button').html("View More");
                    $('#glossaryModal .action-button').attr('href', "/");
                    // Set the disabled status
                    $('#glossaryModal .action-button').removeClass("disabled");
                });
        }
    };
})(jQuery, Drupal);
