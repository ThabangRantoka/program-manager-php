"use strict";

const card = document.getElementById("auth-card");
const loginPanel = document.getElementById("login-panel");
const registerPanel = document.getElementById("register-panel");

let focusTimer;

function showPanel(mode) {
  const registering = mode === "register";

  const activePanel = registering ? registerPanel : loginPanel;
  const inactivePanel = registering ? loginPanel : registerPanel;

  clearTimeout(focusTimer);

  // Move focus away before hiding the previous face.
  if (inactivePanel.contains(document.activeElement)) {
    document.activeElement.blur();
  }

  inactivePanel.inert = true;
  inactivePanel.setAttribute("aria-hidden", "true");

  activePanel.inert = false;
  activePanel.removeAttribute("aria-hidden");

  card.classList.toggle("flipped", registering);

  const reducedMotion = window.matchMedia(
    "(prefers-reduced-motion: reduce)"
  ).matches;

  focusTimer = window.setTimeout(() => {
    activePanel.querySelector("h1").focus({ preventScroll: true });
  }, reducedMotion ? 0 : 900);
}

document.querySelectorAll("[data-switch]").forEach((link) => {
  link.addEventListener("click", (event) => {
    // Keep normal browser behaviour for opening a link in another tab.
    if (
      event.ctrlKey ||
      event.metaKey ||
      event.shiftKey ||
      event.altKey
    ) {
      return;
    }

    event.preventDefault();
    showPanel(link.dataset.switch);
  });
});

document.querySelectorAll("[data-password]").forEach((button) => {
  const input = document.getElementById(button.dataset.password);

  if (!input) {
    return;
  }

  button.hidden = false;

  const fieldName =
    input.id === "confirm-password" ? "confirm password" : "password";

  button.addEventListener("click", () => {
    const show = input.type === "password";

    input.type = show ? "text" : "password";

    button.setAttribute("aria-pressed", String(show));
    button.setAttribute(
      "aria-label",
      (show ? "Hide " : "Show ") + fieldName
    );
  });
});