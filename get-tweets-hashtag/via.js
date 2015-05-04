$( document ).ready(function() {
    $('.quick-tweet-tweetbutton .start').on( 'click', function() {
        $(this).parent().prepend('<textarea id="quick-tweet-area">' + $(this).attr('text') + '</textarea>');
        $('.tweet-it').show();
        $(this).remove();
    });
    $('.quick-tweet-tweetbutton .tweet-it').on( 'click', function() {
        $(this).attr('href', 'https://twitter.com/home?status=' + encodeURIComponent($('#quick-tweet-area').val()));
    });
});
