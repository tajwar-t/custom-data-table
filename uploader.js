jQuery(document).ready(function ($) {
  let mediaUploader;

  // Handle Upload button
  $(document).on('click', '.select-cdt-image', function (e) {
    e.preventDefault();

    const button = $(this);
    const cell = button.closest('.cdt-cell');
    const input = cell.find('.cdt-image-url');
    const preview = cell.find('.cdt-preview');

    // Reuse or create a new media uploader
    mediaUploader = wp.media({
      title: 'Select or Upload an Image',
      button: {
        text: 'Use this image',
      },
      multiple: false
    });

    mediaUploader.on('select', function () {
      const attachment = mediaUploader.state().get('selection').first().toJSON();
      input.val(attachment.url);

      // Set preview with remove button
      preview.html(
        `<img src="${attachment.url}" style="max-width:100px; display:block; margin-bottom:5px;">
         <button class="button button-small remove-cdt-image">X</button>`
      );
    });

    mediaUploader.open();
  });

  // Handle Remove Image button
  $(document).on('click', '.remove-cdt-image', function (e) {
    e.preventDefault();

    const cell = $(this).closest('.cdt-cell');
    const input = cell.find('.cdt-image-url');
    const preview = cell.find('.cdt-preview');

    input.val('');
    preview.empty();
  });
});
