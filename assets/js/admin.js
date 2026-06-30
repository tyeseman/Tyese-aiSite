(function () {
  document.addEventListener("DOMContentLoaded", function () {
    var root = document.querySelector(".tyese-aisite-admin");
    if (root && ["queued", "running"].indexOf(root.getAttribute("data-tyese-build-state")) !== -1) {
      window.setTimeout(function () {
        window.location.reload();
      }, 8000);
    }

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
