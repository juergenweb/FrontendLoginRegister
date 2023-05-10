/*
JavaScript file for FrontendLoginRegister module
contains no JQuery - pure JavaScript
*/


/**
 * Change the image in the preview depending on the image selected in the input type file
 * @param event
 */
function showPreview(event){

    if(event.target.files.length > 0){
        let id = event.target.id;

        let old_img = document.getElementById(id + "-image");
        if(old_img){
            let old_src = old_img.src;
            // write it as data-attribute to the preview wrapper container
            let preview_wrapper = document.getElementById(id + "-preview");
            preview_wrapper.dataset.oldsrc = old_src;
        }

        let file = event.target.files[0];
        // check if file is present and it is an image
        if(file && file["type"].split('/')[0] === "image"){
            let src = URL.createObjectURL(event.target.files[0]);
            let wrapper = document.getElementById(id + "-preview");
            let width = wrapper.style.width;
            let preview = document.getElementById(id + "-image");
            if(preview){
                preview.src = src;
                preview.style.width = width;
            } else
                wrapper.innerHTML = '<img id="'+id+'-image" alt="'+src+'" style="width:'+width+'" src="'+src+'">';
        }
    }
}

/**
 * Show or hide the image depending on if checkbox is checked or not
 * @param checkbox
 */
function removePreview(checkbox){

    let id = checkbox.id;
    let image_id = id.replace("remove", "image");
    let preview = document.getElementById(image_id);

    if(preview){
        if (checkbox.checked) {
            preview.style.display = "none"; // hide the image
        } else {
            preview.style.display = "block"; // show the image again
        }
    }
}



/**
 * Remove the image preview if empty upload field link is clicked
 * @param event
 */
function removeImageTag(event){
    let id = event.id;
    let image_tag_id = id.replace("clear", "image");
    let image_tag = document.getElementById(image_tag_id);
    // check if data-oldsrc is present
    let preview_wrapper_id = id.replace("clear", "preview");
    let preview_wrapper = document.getElementById(preview_wrapper_id);
    if(preview_wrapper){
        if(preview_wrapper.dataset.oldsrc){
            image_tag.src = preview_wrapper.dataset.oldsrc;
        } else {
            image_tag.remove();
        }
    }

}


document.onreadystatechange = onReady;

function onReady() {
    if (document.readyState == "complete") {

        /**
         * Javascript counter in seconds
         * Informs the user about how long the TFA code is valid until it expires
         * Runs only if minTime was set and the form was submitted to fast
         */

        let el = document.getElementById("expirationcounter");
        if (el) {
            let timeleft = parseInt(el.innerText);
            let downloadTimer = setInterval(function () {
                if (timeleft <= 0) {
                    clearInterval(downloadTimer);
                }
                el.innerText = timeleft;
                timeleft -= 1;
            }, 1000);
        }


    }
}
