jQuery(function($) {
  // Aktiviert Sortable auf jeder Serienliste
  $('.mic-series-list').each(function() {
    $(this).sortable({
      placeholder: 'mic-sort-placeholder',
      items: '> li.mic-series-item',
      tolerance: 'pointer'
    });
  });

  $('#mic-save-order').on('click', function(e) {
    e.preventDefault();

    const payload = {};
    $('.mic-series-list').each(function() {
      const termId = $(this).data('term-id');
      const order = [];
      $(this).find('li.mic-series-item').each(function() {
        const pid = $(this).data('post-id');
        if (pid) order.push(pid);
      });
      payload[termId] = order;
    });

    $(this).prop('disabled', true).text('Speichere ...');

    $.post(MicGallery.ajaxUrl, {
      action: 'mic_gallery_save_order',
      nonce: MicGallery.nonce,
      order: JSON.stringify(payload)
    })
    .done(function(res) {
      if (res && res.success) {
        alert('Reihenfolge gespeichert.');
        // Aktualisiere die angezeigte Reihenfolge
        $('.mic-series-list').each(function() {
          $(this).find('li.mic-series-item').each(function(index) {
            $(this).find('.mic-order').text(index + 1);
          });
        });
      } else {
        alert('Fehler: ' + (res && res.data && res.data.message ? res.data.message : 'Unbekannt'));
      }
    })
    .fail(function() {
      alert('Netzwerkfehler beim Speichern.');
    })
    .always(() => {
      $('#mic-save-order').prop('disabled', false).text('Reihenfolge speichern');
    });
  });
});