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

  // FAQ accordion
  var btns = document.querySelectorAll(".faq-item button");
  for (var i = 0; i < btns.length; i++) {
    btns[i].addEventListener("click", function () {
      var ex = this.getAttribute("aria-expanded") === "true";
      this.setAttribute("aria-expanded", ex ? "false" : "true");
    });
  }

  // Current year
  var y = document.getElementById("year");
  if (y) { y.textContent = new Date().getFullYear(); }
})();
