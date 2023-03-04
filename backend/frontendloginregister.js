/*
JavaScript file for FrontendLogin module backend
contains no JQuery - pure JavaScript
*/


document.onreadystatechange = onReady;

function onReady() {
    if (document.readyState === "complete") {

        // get elements by ID
        let delete_text = document.getElementById("input_delete_text");
        let days_to_delete = document.getElementById("days-to-delete");
        let delete_input = document.getElementById("Inputfield_input_delete");
        let remind_input = document.getElementById("Inputfield_input_remind");
        let remind_desc_text = document.getElementById("reminder-mail-text");

        // show/hide text inside input_delete description
        function showHideRemindText(){
            if(remind_input.value > 0){
                remind_desc_text.removeAttribute("style");
                remind_desc_text.style.display = "inline-block";
            } else {
                remind_desc_text.removeAttribute("style");
                remind_desc_text.style.display = "none";
            }
        }

        // run show/hide remind text in input_delete description on page load
        showHideRemindText();

        // calculate number of days as sum of input_remind and input_delete
        function calculation() {
            let days = Number(delete_input.value) + Number(remind_input.value);
            days_to_delete.innerText = days;
        }

        // calculate days to delete on delete_input change
        function calculateDeleteDate() {
            if(delete_input.value > 0){
                calculation();
            }
            showHideRemindText();
        }

        calculateDeleteDate();

        // add event listeners
        remind_input.addEventListener("change", calculateDeleteDate);
        delete_input.addEventListener("change", showHideText);

        function showHideText(){
            if(delete_input.value > 0){
                delete_text.removeAttribute("style");
                delete_text.display = "inline-block";
                calculateDeleteDate();

            } else {
                delete_text.removeAttribute("style");
                delete_text.style.display = "none";
            }
        }
    }

}
