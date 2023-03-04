/*
JavaScript file for FrontendLoginRegister module
contains no JQuery - pure JavaScript
*/

/*
Javascript counter in seconds
Informs the user about how long the TFA code is valid until it expires
Runs only if minTime was set and the form was submitted to fast
*/

document.onreadystatechange = onReady;

function onReady() {
    if (document.readyState == "complete") {
        let el = document.getElementById('expirationcounter');
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
