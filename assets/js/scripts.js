$('document').ready(function () {
  // Simple AJAX listeners
  $(document).bind("ajaxSend", function () {
    $('.btn-primary').attr('disabled', 'disabled');
  }).bind("ajaxComplete", function () {
    $('.btn-primary').removeAttr('disabled');
  });

  // Show results modal
  $('#get-results').click(function () {
    $('.alert').remove();

    var validated = 1;

    $('.validate').each(function () {
      if ($(this).val() === '') {
        $('#subtitle').after('<div class="alert alert-danger"><strong>Whoops!</strong> You must fill out all of the fields before searching!</div>');
        validated = 0;
        return false;
      }

      $('#' + $(this).attr('id') + '-answer').text($(this).val());
    });

    $('#price_min,#price_max,#num_baths,#num_beds').each(function () {
      var numbers_comma = /^[0-9,]*$/;

      if (!numbers_comma.test($(this).val())) {
        var label = $("label[for='" + $(this).attr('id') + "']").text();
        $('#subtitle').after('<div class="alert alert-danger"><strong>Whoops!</strong> The value of ' + label + ' must be a number!</div>');
        validated = 0;
      }
    });

    if (validated == 1) {
      $('#get-results-modal').modal('show');

      var retargeting = $('#retargeting').val();
      if (retargeting !== '') {
        (function () {
          var _fbq = window._fbq || (window._fbq = []);
          if (!_fbq.loaded) {
            var fbds = document.createElement('script');
            fbds.async = true;
            fbds.src = '//connect.facebook.net/en_US/fbds.js';
            var s = document.getElementsByTagName('script')[0];
            s.parentNode.insertBefore(fbds, s);
            _fbq.loaded = true;
          }
          _fbq.push(['addPixelId', retargeting]);
        })();
        window._fbq = window._fbq || [];
        window._fbq.push(['track', 'PixelInitialized', {}]);
      }
    }

    return false;
  });

  // Submit quiz results
  $('#submit-results').click(function (e) {
    e.preventDefault();
    var form = $('#house-hunter');

    $.ajax({
      type: 'POST',
      url: HouseHunter.ajaxurl,
      data: form.serialize(),
      dataType: 'json',
      beforeSend: function () {
        $('#submit-results').html('<i class="fa fa-spinner fa-spin"></i> Processing...');
      },
      async: true,
      success: function (response) {
        var conversion = $('#conversion').val();

        if (conversion !== '') {
          (function () {
            var _fbq = window._fbq || (window._fbq = []);
            if (!_fbq.loaded) {
              var fbds = document.createElement('script');
              fbds.async = true;
              fbds.src = '//connect.facebook.net/en_US/fbds.js';
              var s = document.getElementsByTagName('script')[0];
              s.parentNode.insertBefore(fbds, s);
              _fbq.loaded = true;
            }
          })();
          window._fbq = window._fbq || [];
          window._fbq.push(['track', conversion, {'value': '0.00', 'currency': 'USD'}]);
        }

        setTimeout(function () {
          $('#get-results-modal').modal('hide');
          $('body').removeClass('modal-open');
          $('#house-hunter,.modal-backdrop').remove();
          $('.results').show();
        }, 1000);
      }
    });
  });
});