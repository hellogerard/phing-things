/**
 * This file will insert the date in the main page
 */

$(function() {
    // array of day names
    var dayNames = new Array("Sunday","Monday","Tuesday","Wednesday", "Thursday","Friday","Saturday");

    // array of month Names
    var monthNames = new Array("January","February","March","April","May","June","July", "August","September","October","November","December");

    var now = new Date();
    $('#datediv').html(dayNames[now.getDay()] + ", " + monthNames[now.getMonth()] + " " + now.getDate() + ", " + now.getFullYear());
});
