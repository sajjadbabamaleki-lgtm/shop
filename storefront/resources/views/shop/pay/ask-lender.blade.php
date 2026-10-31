{{--
    Asks each instalment button's own lender whether it will finance this
    order, and reveals the button with the lender's words in it.

    Shared by اسنپ‌پی's partial and ترب‌پی's, and printed **once** however many
    of them are on the page — two copies would each walk every form and ask
    every lender twice.

    **Nothing here writes a sentence.** Both providers require the title and
    description to be printed exactly as `eligible` returned them, and if they
    send none the button stays hidden rather than opening empty. Every other
    failure is silent on purpose: a lender's button not appearing costs a
    shopper nothing, because the card button beside it is untouched.
--}}
@once
<script>
    (function () {
        // Printed at the first lender's button, so a second lender's form is
        // not parsed yet when this runs. Waiting for the document finds both.
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', ask);
        } else {
            ask();
        }

        function ask() {
            var forms = document.querySelectorAll('form[data-vp-instalments]');

            Array.prototype.forEach.call(forms, function (form) {
                fetch(form.getAttribute('data-vp-instalments'), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin'
                })
                    .then(function (answer) { return answer.ok ? answer.json() : null; })
                    .then(function (said) {
                        if (!said || said.eligible !== true || !said.title) {
                            return;
                        }

                        form.querySelector('.vp-snapp-title').textContent = said.title;
                        form.querySelector('.vp-snapp-say').textContent = said.description || '';
                        form.hidden = false;
                    })
                    .catch(function () {});
            });
        }
    }());
</script>
@endonce
