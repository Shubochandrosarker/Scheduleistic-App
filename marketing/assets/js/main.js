/* Scheduleistic — minimal vanilla JS: nav toggle, FAQ accordion, footer year */
(function () {
  "use strict";

  // Mobile nav toggle
  var toggle = document.querySelector(".nav-toggle");
  var links = document.getElementById("nav-links");
  if (toggle && links) {
    toggle.addEventListener("click", function () {
      var open = links.classList.toggle("open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
  }

  // Nav dropdown ("Product") — click to open/close, works the same way as
  // a mobile accordion (CSS handles the visual difference between the two).
  var dropdowns = document.querySelectorAll(".nav-item-dropdown");
  var closeDropdown = function (dd) {
    dd.classList.remove("open");
    var t = dd.querySelector(".nav-dropdown-toggle");
    if (t) t.setAttribute("aria-expanded", "false");
  };
  var closeAllDropdowns = function () {
    for (var d = 0; d < dropdowns.length; d++) { closeDropdown(dropdowns[d]); }
  };
  for (var i = 0; i < dropdowns.length; i++) {
    (function (dd) {
      var dToggle = dd.querySelector(".nav-dropdown-toggle");
      if (!dToggle) return;
      dToggle.addEventListener("click", function (e) {
        e.stopPropagation();
        var isOpen = dd.classList.contains("open");
        closeAllDropdowns();
        if (!isOpen) {
          dd.classList.add("open");
          dToggle.setAttribute("aria-expanded", "true");
        }
      });
    })(dropdowns[i]);
  }
  if (dropdowns.length) {
    document.addEventListener("click", function (e) {
      for (var d = 0; d < dropdowns.length; d++) {
        if (!dropdowns[d].contains(e.target)) { closeDropdown(dropdowns[d]); }
      }
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") { closeAllDropdowns(); }
    });
  }

  // FAQ accordion
  var btns = document.querySelectorAll(".faq-item button");
  for (i = 0; i < btns.length; i++) {
    btns[i].addEventListener("click", function () {
      var ex = this.getAttribute("aria-expanded") === "true";
      this.setAttribute("aria-expanded", ex ? "false" : "true");
    });
  }

  // Current year
  var y = document.getElementById("year");
  if (y) { y.textContent = new Date().getFullYear(); }

  // Scroll reveal for elements marked .reveal. Content is visible by
  // default (see styles.css) — this code is the ONLY thing that ever hides
  // it, and only after it has successfully started running. If this script
  // fails to load or errors out, nothing on the page is ever hidden.
  var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var revealEls = document.querySelectorAll(".reveal");
  var reveal = function (el) { el.style.opacity = ""; el.style.transform = ""; };
  if (revealEls.length && !reduced && "IntersectionObserver" in window) {
    revealEls.forEach(function (el) {
      el.style.opacity = "0";
      el.style.transform = "translateY(18px)";
    });
    var io = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            reveal(entry.target);
            io.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.15, rootMargin: "0px 0px -40px 0px" }
    );
    revealEls.forEach(function (el) { io.observe(el); });
    // Safety net: force everything visible after a few seconds regardless,
    // in case anything was never observed as intersecting.
    setTimeout(function () { revealEls.forEach(reveal); }, 4000);
  }
})();
