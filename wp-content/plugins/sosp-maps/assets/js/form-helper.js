/**
 * SOSP Maps Form Helper
 * Handles URL scraping and form field population
 */

jQuery(document).ready(function($) {
    var $fetchBtn = $('#sosp_fetch_btn');
    var $urlInput = $('#sosp_page_url');
    var $status = $('#sosp_fetch_status');

    if (!$fetchBtn.length) {
        return; // Form not present
    }

    $fetchBtn.on('click', function(e) {
        e.preventDefault();
        var url = $urlInput.val().trim();

        if (!url) {
            showStatus('Indtast en URL', 'error');
            return;
        }

        $fetchBtn.prop('disabled', true).text('Henter...');
        $status.removeClass('sosp-success sosp-error').addClass('sosp-loading').text('Henter data...').show();

        $.ajax({
            url: sosp_maps_form.ajax_url,
            type: 'POST',
            data: {
                action: 'sosp_maps_scrape_url',
                url: url
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    
                    // Populate form fields
                    if (data.navn) {
                        $('#gr_navn').val(data.navn);
                    }
                    if (data.add) {
                        $('#gr_add').val(data.add);
                    }
                    if (data.mail) {
                        $('#gr_mail').val(data.mail);
                    }
                    if (data.side) {
                        $('#gr_side').val(data.side);
                    }

                    showStatus('Data hentet! Gennemgå venligst informationerne før gem.', 'success');
                } else {
                    showStatus(response.data.message || 'Fejl ved hentning af data', 'error');
                }
            },
            error: function() {
                showStatus('Fejl ved kommunikation med serveren', 'error');
            },
            complete: function() {
                $fetchBtn.prop('disabled', false).text('Hent data');
            }
        });
    });

    function showStatus(message, type) {
        $status.removeClass('sosp-loading sosp-success sosp-error').addClass('sosp-' + type).text(message).show();
        
        if (type === 'success') {
            setTimeout(function() {
                $status.fadeOut();
            }, 5000);
        }
    }

    // Allow Enter key in URL input to trigger fetch
    $urlInput.on('keypress', function(e) {
        if (e.which === 13) {
            $fetchBtn.click();
            return false;
        }
    });
});
