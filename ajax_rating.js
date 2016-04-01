/**
 * Created by claudia on 28.03.2016.
 */

jQuery(document).ready(function($) {

    // when one of the rating buttons is clicked
    // send the up/down vote to the server
    // change the state of the clicked button

    var articleID = $('article').attr('id');
    var postID = articleID.split("-")[1];


   getVotingScore();

    function getVotingScore() {
        var request = $.ajax({
            method: "GET",
            url: RatingAjaxArray.ajaxurl,
            data: {
                postID: postID,
                action : 'my_ajax_submit'
            }
        });
        request.done(function( msg ) {
            console.log(msg) ;
            console.log("success");
            $('.totalScoreValue').text(msg);
            $('.totalScoreText').text(' points');
        });
        request.fail(function( jqXHR, textStatus ) {
            console.log(textStatus);
            console.log("failure");
        });
    }


    $('.dashicons-thumbs-up, .dashicons-thumbs-down').on('click', function() {
        var selectedRating;
        var cancelledVote = false; // the user voted accidentally, or he changed his mind
        var changedVote = false; // the user wants to change his vote from down to up or vice-versa

        if($(this).hasClass('button_pressed')) {
            //the button is already pressed
            // the user wants to take back his vote
            cancelledVote = true;
        }

        if ($(this).hasClass('dashicons-thumbs-up')) {
            alert("you pressed up");
            selectedRating = "up";

            if ( $('.dashicons-thumbs-down').hasClass('button_pressed') ) {
                changedVote = true;
            }

        } else if ($(this).hasClass('dashicons-thumbs-down')) {
            alert("you pressed down");
            selectedRating = "down";
            if ( $('.dashicons-thumbs-up').hasClass('button_pressed') ) {
                changedVote = true;
            }

        } else {
            return;
        }

        var request = $.ajax({
                        method: "POST",
                        url: RatingAjaxArray.ajaxurl,
                        data: {
                            // Declare the parameters to send along with the request
                            action : 'my_ajax_submit',
                            postID : postID,
                            rating : selectedRating,
                            ratingNonce : RatingAjaxArray.ratingNonce,
                            cancelledVote: cancelledVote,
                            changedVote: changedVote
                        }
                      });

        request.done(function( msg ) {
            getVotingScore();
            if (cancelledVote) {
                // remove button_pressed class
                $(".dashicons-thumbs-" + selectedRating).removeClass("button_pressed");
            } else {
                $(".dashicons-thumbs-" + selectedRating).addClass(" button_pressed ");
            }

            if ( changedVote ) {
                if(selectedRating === "up") {
                    $(".dashicons-thumbs-down").removeClass("button_pressed");
                } else {
                    $(".dashicons-thumbs-up").removeClass("button_pressed");
                }
            }
        });

        request.fail(function( jqXHR, textStatus ) {
            console.log("status " + textStatus);
        });

    });

});


