/**
 * This file will enlarge/shrink font size on the page
 */

$(function() {
    var size = 1;

    $('#enlarge').click(function() {
        size += 0.2;
        $('body').css('font-size', size + 'em');
    });

    $('#shrink').click(function() {
        size -= 0.2;
        $('body').css('font-size', size + 'em');
    });
});
