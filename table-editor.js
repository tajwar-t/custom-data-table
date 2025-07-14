jQuery(document).ready(function($) {
    $('#cdt-update-grid').on('click', function () {
        const rows = parseInt($('#cdt-rows').val());
        const cols = parseInt($('#cdt-cols').val());

        if (rows < 1 || cols < 1) return;

        const existingData = {};
        $('.cdt-cell').each(function () {
            const $cell = $(this);
            const nameAttr = $cell.find('input.cdt-image-url').attr('name');
            const match = nameAttr.match(/cdt_cells\[(\d+)\]\[(\d+)\]/);
            if (!match) return;

            const i = parseInt(match[1]);
            const j = parseInt(match[2]);

            existingData[`${i}_${j}`] = {
                image: $cell.find('input.cdt-image-url').val(),
                text: $cell.find('textarea').val()
            };
        });

        let html = '';
        for (let i = 0; i < rows; i++) {
            html += "<div class='cdt-row' style='display:flex;'>";
            for (let j = 0; j < cols; j++) {
                const key = `${i}_${j}`;
                const cell = existingData[key] || { image: '', text: '' };
                const field = `cdt_cells[${i}][${j}]`;

                html += `
                    <div class='cdt-cell styled-cell' style='margin: 5px; min-width:160px;'>
                        <strong>Image:</strong><br>
                        <input type='hidden' name='${field}[image]' class='cdt-image-url' value='${cell.image}'>
                        <button class='button select-cdt-image'>Upload</button>
                        <div class='cdt-preview' style='margin-top:5px;'>
                            ${cell.image ? `<img src="${cell.image}" style="max-width:100px;">` : ''}
                        </div>
                        <br><strong>Text:</strong><br>
                        <textarea name='${field}[text]' rows='2' style='width:90%;'>${cell.text}</textarea>
                    </div>
                `;
            }
            html += "</div>";
        }

        $('#cdt-cell-grid').html(html);
    });
    $(document).on('click', '.remove-cdt-image', function(e) {
        e.preventDefault();

        const cell = $(this).closest('.cdt-cell');
        cell.find('.cdt-image-url').val(''); // Clear hidden input
        cell.find('.cdt-preview').html('');  // Remove preview
    });
});
