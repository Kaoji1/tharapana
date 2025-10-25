// target all required elements
const filterButtons = document.querySelectorAll(".nav-item[data-name]");
const filterImages = document.querySelectorAll(".gallery .image");

window.onload = () => {
  // add click event for each filter button
  filterButtons.forEach(button => {
    button.addEventListener("click", selectedItem => {
      selectedItem.preventDefault();

      // remove existing active class and add to current
      document.querySelectorAll(".nav-item").forEach(btn => {
        btn.classList.remove("active");
      });
      button.classList.add("active");

      let filterName = button.getAttribute("data-name"); // current category name

      filterImages.forEach(image => {
        let imageCategory = image.getAttribute("data-name");

        if (filterName === "all" || filterName === imageCategory) {
          image.classList.remove("hide");
          image.classList.add("show");
        } else {
          image.classList.remove("show");
          image.classList.add("hide");
        }
      });
    });
  });

  // add preview click to all images
  filterImages.forEach(image => {
    image.setAttribute("onclick", "preview(this)");
  });
};

// fullscreen image preview function
const previewBox = document.querySelector(".preview-box"),
      previewImg = previewBox.querySelector("img"),
      closeIcon = previewBox.querySelector(".icon"),
      shadow = document.querySelector(".shadow");

function preview(element) {
  let selectedPrevImg = element.querySelector("img").src;
  previewImg.src = selectedPrevImg;

  previewBox.classList.add("show");
  shadow.classList.add("show");
  document.body.style.overflow = "hidden";

  closeIcon.onclick = () => {
    previewBox.classList.remove("show");
    shadow.classList.remove("show");
    document.body.style.overflow = "auto";
  };
}
