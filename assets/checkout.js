/* ============================================================
   Stripe Checkout launcher — MMS Shotokan pricing page
   - Loads display prices from /api/prices.php
   - On click, asks /api/create-checkout.php for a Checkout
     Session URL and redirects the browser to Stripe.
   ============================================================ */
(function () {
  "use strict";

  var API_BASE = "api/";

  function money(cents) {
    return (cents / 100).toLocaleString("en-CA", {
      minimumFractionDigits: cents % 100 === 0 ? 0 : 2,
      maximumFractionDigits: 2
    });
  }

  function status(msg, kind) {
    var el = document.querySelector("[data-checkout-status]");
    if (!el) return;
    el.textContent = msg || "";
    el.className = "plans-note" + (kind ? " is-" + kind : "");
  }

  // 1) Populate displayed prices.
  fetch(API_BASE + "prices.php", { headers: { Accept: "application/json" } })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      document.querySelectorAll("[data-price]").forEach(function (el) {
        var key = el.getAttribute("data-price");
        if (data[key] && typeof data[key].amount === "number") {
          el.textContent = money(data[key].amount);
        }
      });
    })
    .catch(function () {
      // Prices stay as "—"; not fatal for checkout.
    });

  // 2) Wire buy buttons.
  document.querySelectorAll(".plan-buy").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var plan = btn.getAttribute("data-plan");
      var original = btn.textContent;
      btn.disabled = true;
      btn.textContent = "Redirecting…";
      status("Opening secure Stripe checkout…", "pending");

      fetch(API_BASE + "create-checkout.php", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({ plan: plan })
      })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
          if (res.ok && res.j.url) {
            window.location.href = res.j.url;
          } else {
            throw new Error((res.j && res.j.error) || "Could not start checkout");
          }
        })
        .catch(function (err) {
          status(
            "Sorry — checkout isn't available right now. Please email mms.shotokan.karate@gmail.com or call 416-524-4007. (" +
              err.message + ")",
            "error"
          );
          btn.disabled = false;
          btn.textContent = original;
        });
    });
  });
})();
