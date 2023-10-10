/*
JavaScript file for FrontendLoginRegister module
contains no JQuery - pure JavaScript
*/


/**
 * Change the image in the preview depending on the image selected in the input type file
 * @param event
 */
function showPreview(event) {

    if (event.target.files.length > 0) {
        let id = event.target.id;

        let old_img = document.getElementById(id + "-image");
        let preview_wrapper = document.getElementById(id + "-preview");
        if (old_img) {
            let old_src = old_img.src;
            // write it as data-attribute to the preview wrapper container
            preview_wrapper.dataset.oldsrc = old_src;
        }
        let file = event.target.files[0];
        // check if file is present and it is an image
        if (file && file["type"].split('/')[0] === "image") {
            let src = URL.createObjectURL(event.target.files[0]);
            if (old_img) {
                old_img.src = src;
            } else {
                let image_width = preview_wrapper.dataset.width;
                let image_class = preview_wrapper.dataset.class;
                preview_wrapper.innerHTML = '<img id="' + id + '-image" class="' + image_class + '" alt="' + src + '" src="' + src + '" style="width:' + image_width + ';">';
            }
        }
    }
}

/**
 * Show or hide the image depending on if checkbox is checked or not
 * @param checkbox
 */
function removePreview(checkbox) {

    let id = checkbox.id;
    let image_id = id.replace("remove", "preview");
    let preview = document.getElementById(image_id);

    if (preview) {
        if (checkbox.checked) {
            preview.style = "display:none"; // hide the image
        } else {
            preview.style = "display:block"; // show the image again
        }
    }
}


/**
 * Remove the image preview if empty upload field link is clicked
 * @param event
 */
function removeImageTag(event) {
    let id = event.id;
    let image_tag_id = id.replace("clear", "image");
    let image_tag = document.getElementById(image_tag_id);
    // check if data-oldsrc is present
    let preview_wrapper_id = id.replace("clear", "preview");
    let preview_wrapper = document.getElementById(preview_wrapper_id);
    if (preview_wrapper) {
        if (preview_wrapper.dataset.oldsrc) {
            image_tag.src = preview_wrapper.dataset.oldsrc;
        } else {
            image_tag.remove();
        }
    }

}

