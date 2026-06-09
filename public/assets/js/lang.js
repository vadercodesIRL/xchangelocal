// google translate setup - called by google's script when it loads
function googleTranslateElementInit() {
    new google.translate.TranslateElement({
        pageLanguage: 'en',
        includedLanguages: 'en,zu',
        layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
        autoDisplay: false
    }, 'google_translate_element');
}

// hide the toolbar google injects at the top of the page
function suppressGoogleUI() {
    var bar = document.querySelector('.goog-te-banner-frame');
    if (bar) bar.style.setProperty('display', 'none', 'important');

    var skip = document.querySelector('.skiptranslate');
    if (skip) skip.style.setProperty('display', 'none', 'important');

    if (document.body.style.top) document.body.style.top = '';
}

// read which language is currently active from the google cookie
function getCurrentLang() {
    var match = document.cookie.match(/googtrans=\/en\/([a-z]{2})/);
    return (match && match[1] !== 'en') ? match[1] : 'en';
}

// set the googtrans cookie for a non-English language
function setTranslateCookie(lang) {
    var host = location.hostname;
    var base = 'googtrans=/en/' + lang + '; path=/';
    document.cookie = base;
    if (host.indexOf('.') !== -1) {
        document.cookie = base + '; domain=.' + host;
    }
}

// delete the googtrans cookie from all domain/path combinations
function clearTranslateCookie() {
    var host = location.hostname;
    var past = '; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
    document.cookie = 'googtrans=' + past;
    document.cookie = 'googtrans=; domain=' + host + past;
    if (host.indexOf('.') !== -1) {
        document.cookie = 'googtrans=; domain=.' + host + past;
    }
}

// trigger isiZulu translation via widget, reload as fallback
function triggerTranslate(lang) {
    function attempt(tries) {
        var widget = document.getElementById('google_translate_element');
        var select = widget && widget.querySelector('select.goog-te-combo');
        if (select) {
            select.value = lang;
            select.dispatchEvent(new Event('change'));
            setTimeout(suppressGoogleUI, 400);
            setTimeout(suppressGoogleUI, 1200);
        } else if (tries > 0) {
            setTimeout(function () { attempt(tries - 1); }, 300);
        } else {
            setTranslateCookie(lang);
            location.reload();
        }
    }
    attempt(10);
}

// highlight the active button in every lang-switch on the page
function updateSwitch(lang) {
    document.querySelectorAll('.lang-switch__btn').forEach(function (btn) {
        btn.classList.toggle('lang-switch__btn--active', btn.dataset.lang === lang);
    });
}

// wire up the EN / ZU two-button switch
function langSwitch() {
    var btns = document.querySelectorAll('.lang-switch__btn');
    if (!btns.length) return;

    updateSwitch(getCurrentLang());

    btns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var lang = this.dataset.lang;
            if (lang === getCurrentLang()) return;

            updateSwitch(lang);

            if (lang === 'en') {
                // clearing the cookie + reload is the only reliable reset
                clearTranslateCookie();
                location.reload();
            } else {
                setTranslateCookie(lang);
                triggerTranslate(lang);
            }
        });
    });

    var observer = new MutationObserver(suppressGoogleUI);
    observer.observe(document.body, { childList: true, subtree: true });
}

document.addEventListener('DOMContentLoaded', langSwitch);
