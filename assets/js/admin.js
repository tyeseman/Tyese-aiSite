(function () {
  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".tyese-aisite-admin form").forEach(function (form) {
      form.addEventListener("submit", function () {
        var button = form.querySelector("button[type='submit']");
        if (button) {
          button.dataset.originalText = button.textContent;
          button.textContent = "Working...";
          button.disabled = true;
        }
      });
    });
  });
})();
