"use strict";

document.querySelectorAll('input[type="password"]').forEach((input) => {
  const wrapper = document.createElement("div");
  wrapper.className = "password-field";

  input.before(wrapper);
  wrapper.append(input);

  const fieldName =
    input.id === "confirm_password" ? "confirm password" : "password";

  const button = document.createElement("button");
  button.type = "button";
  button.className = "password-toggle";
  button.textContent = "Show";

  button.setAttribute("aria-controls", input.id);
  button.setAttribute("aria-label", "Show " + fieldName);
  button.setAttribute("aria-pressed", "false");

  button.addEventListener("click", () => {
    const show = input.type === "password";

    input.type = show ? "text" : "password";
    button.textContent = show ? "Hide" : "Show";

    button.setAttribute("aria-pressed", String(show));
    button.setAttribute(
      "aria-label",
      (show ? "Hide " : "Show ") + fieldName
    );
  });

  wrapper.append(button);
});