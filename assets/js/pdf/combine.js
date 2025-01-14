require("../../css/pdf/combine.scss");

let interval = null;

const scrollConsole = () => {
  document.querySelector("iframe").contentWindow.scrollTo(0, 99999999);
};

const setMessage = (info) => {
  const message = document.getElementById("message");
  if (message) {
    message.classList.remove("alert-danger", "alert-success", "alert-info");
    if (info.message) {
      message.innerHTML = info.message;
      message.classList.add("alert-info");
    } else {
      message.innerHTML = JSON.stringify(info, null, 2);
      if (info.exit_code !== 0) {
        message.classList.add("alert-danger");
      } else {
        message.classList.add("alert-success");
      }
    }
  }
};

window.processCompleted = (info) => {
  setMessage(info);
  clearInterval(interval);
  scrollConsole();
};

document.querySelector("form").addEventListener("submit", () => {
  setMessage({
    message: "Running command. Please be very patient …",
  });
  interval = setInterval(scrollConsole, 100);
});

// @todo Set this dynamically
document.getElementById("main").style.height = "800px";
