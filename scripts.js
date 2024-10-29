jQuery(function($) {
    $('#folderlistscroll').jstree({
        plugins: ["themes", "html_data", "checkbox"],
        checkbox: {
            two_state: true,
            real_checkboxes: true,
            real_checkboxes_names: function(nod) {
                return ['folders[' + nod[0].id + ']', 1];
            }
        },
        themes: {
            theme: 'classic'
        },
        core: {
            load_open: true,
            animation: 0
        }
    });
});