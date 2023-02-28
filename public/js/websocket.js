// const ws = new WebSocket("ws://192.168.5.178:2020");
// // const ws = new WebSocket("ws://192.168.5.4:2020");
// // const ws = new WebSocket("ws://dohcsmc.online:2020");
//
// const station = $("#user_station").text().trim()
// const role = $("#user_role").text().trim();
// const code = $("#code").text().trim();
// const key = $("meta[name='csrf-token']").attr("content");
// let notification_counter = 0;
//
// //TEXT TO SPEECH
// let speaker = new SpeechSynthesisUtterance();
// speaker.text = "New Request!";
//
// toastr.options = {
//     "positionClass": "toast-bottom-right",
// }
//
// ws.onopen = function () {
//     console.log("Websocket client connected...");
//     const data = {
//         action: "LOGIN",
//         body: {
//             code: code,
//             station: station,
//             role: role,
//             key: key
//         }
//     }
//
//     ws.send(JSON.stringify(data));
// }
//
// ws.onmessage = function (message) {
//     const action = JSON.parse(message.data).action;
//     const body = JSON.parse(message.data).body;
//
//     switch (action) {
//         case "LOGIN":
//             console.log(body.message);
//             break;
//         case "NOTIFY":
//             notification_counter++;
//             toastr.success('New Request')
//             speechSynthesis.speak(speaker);
//             $("#btn_notification").removeClass("btn-secondary");
//             $("#btn_notification").addClass("btn-danger");
//             $("#btn_notification").addClass("blink");
//             $("#btn_notification").find("span").text(notification_counter);
//             break;
//         case "LOGOUT":
//             logout();
//             break;
//     }
// }
//
// ws.onclose = function (err) {
//     console.log(err);
//     // logout();
// }
//
function sendWebsocketServer(data) {
    // ws.send(JSON.stringify(data));
}
