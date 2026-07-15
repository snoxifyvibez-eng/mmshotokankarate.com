/* ============================================================
   Web3Forms submission handler — MMS Shotokan
   Any <form data-web3form> on the site submits here via AJAX,
   shows inline status, and never navigates away.

   Setup: create a free access key at https://web3forms.com
   (use mms.shotokan.karate@gmail.com), then paste it into
   WEB3FORMS_ACCESS_KEY below (or set data-access-key per form).
   ============================================================ */
(function () {
  "use strict";

  // TODO: paste your Web3Forms access key here (see SETUP.md).
  var WEB3FORMS_ACCESS_KEY = "f8a5d476-0dbc-483a-8f96-527ca024aff7";

  var ENDPOINT = "https://api.web3forms.com/submit";

  function statusEl(form) {
    var el = form.querySelector("[data-form-status]");
    if (!el) {
      el = document.createElement("p");
      el.setAttribute("data-form-status", "");
      el.className = "form-status";
      el.setAttribute("role", "status");
      el.setAttribute("aria-live", "polite");
      form.appendChild(el);
    }
    return el;
  }

  function setStatus(form, kind, message) {
    var el = statusEl(form);
    el.className = "form-status is-" + kind;
    el.textContent = message;
  }

  function handle(form) {
    form.addEventListener("submit", function (ev) {
      ev.preventDefault();

      // Honeypot — bots fill hidden fields; humans don't.
      var honey = form.querySelector('input[name="botcheck"]');
      if (honey && honey.checked) return;

      var key = form.getAttribute("data-access-key") || WEB3FORMS_ACCESS_KEY;
      if (!key || key === "YOUR_WEB3FORMS_ACCESS_KEY") {
        setStatus(form, "error",
          "Form isn't configured yet. Add your Web3Forms access key (see SETUP.md).");
        return;
      }

      var submitBtn = form.querySelector('button[type="submit"]');
      var originalLabel = submitBtn ? submitBtn.textContent : "";
      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = "Sending…"; }
      setStatus(form, "pending", "Sending…");

      var data = new FormData(form);
      data.append("access_key", key);
      if (!data.get("subject")) {
        data.append("subject", form.getAttribute("data-subject") || "New submission — MMS Shotokan");
      }
      // Friendly sender name shown in the email.
      data.append("from_name", form.getAttribute("data-from-name") || "MMS Shotokan Website");

      fetch(ENDPOINT, {
        method: "POST",
        headers: { Accept: "application/json" },
        body: data
      })
        .then(function (res) { return res.json().then(function (j) { return { ok: res.ok, j: j }; }); })
        .then(function (r) {
          if (r.ok && r.j.success) {
            var msg = form.getAttribute("data-success") ||
              "Thank you — your message has been received. We'll be in touch soon. 押忍";
            setStatus(form, "success", msg);
            form.reset();
            if (submitBtn) { submitBtn.textContent = "Received 押忍"; submitBtn.classList.add("is-done"); }
          } else {
            throw new Error((r.j && r.j.message) || "Submission failed");
          }
        })
        .catch(function (err) {
          setStatus(form, "error",
            "Sorry — something went wrong. Please email mms.shotokan.karate@gmail.com or call 416-524-4007. (" +
            err.message + ")");
          if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalLabel; }
        });
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    var forms = document.querySelectorAll("form[data-web3form]");
    Array.prototype.forEach.call(forms, handle);
  });
})();
