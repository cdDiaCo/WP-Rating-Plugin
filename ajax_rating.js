/**
 * Created by claudia on 28.03.2016.
 */

jQuery(document).ready(function($) {

    // when one of the rating buttons is clicked
    // send the up/down vote to the server
    // change the state of the clicked button


    var article_list = $("main").find("article");

    if( !article_list.length ) {
        article_list = $("div[role='main']").find("article");
    }

    $.each(article_list, function() {
        var articleID, postID;
        articleID = $(this).attr('id');
        postID = articleID.split("-")[1];
        var header = $(this).find("header");
        if ( !header.length ){
            // we do not have the header element
            // get the first div child
            header = $(this).children().first();
        }
        appendPluginHtml(header);
        getVotingScore(header, postID);
    });

    function appendPluginHtml(header) {
        header.children().eq( 1 ).css("margin-bottom", "20px");

        header.append($('<div></div>')
            .addClass("ratingDiv")
            .append($('<div></div>')
                .addClass("ratingButtons")
                .append($('<span></span>')
                    .addClass("dashicons dashicons-thumbs-up"))
                .append($('<span></span>')
                    .addClass("dashicons dashicons-thumbs-down"))
            )
            .append($('<div></div>')
                .addClass("totalScore")
                .append($('<span></span>')
                    .addClass("totalScoreValue"))
                .append($('<span></span>')
                    .addClass("totalScoreText"))
            )
        );
    }

    function getVotingScore(header, postID) {
        var request = $.ajax({
            method: "GET",
            url: RatingAjaxArray.ajaxurl,
            data: {
                postID: postID,
                action : 'my_ajax_submit',
                dataType: 'json'
            }
        });
        request.done(function( response ) {
            header.find('.totalScoreValue').text(response.totalScore);
            header.find('.totalScoreText').text(' points');
            if( response.voted ) {
                header.find(".dashicons-thumbs-" + response.voteType).addClass(" button_pressed ");
            }
        });
        request.fail(function( jqXHR, textStatus ) {
            console.log(textStatus);
        });
    }

    $('.dashicons-thumbs-up, .dashicons-thumbs-down').on('click', function() {
        var selectedRating;
        //var parentHeader = $(this).closest("header");
        var parentArticle = $(this).closest("article");
        var articleID = parentArticle.attr('id');
        var postID = articleID.split("-")[1];
        var cancelledVote = false; // the user voted accidentally, or he changed his mind
        var changedVote = false; // the user wants to change his vote from down to up or vice-versa

        if($(this).hasClass('button_pressed')) {
            //the button is already pressed
            // the user wants to take back his vote
            cancelledVote = true;
        }

        if ($(this).hasClass('dashicons-thumbs-up')) {
            selectedRating = "up";
            if ( parentArticle.find('.dashicons-thumbs-down').hasClass('button_pressed') ) {
                changedVote = true;
            }
        } else if ($(this).hasClass('dashicons-thumbs-down')) {
            selectedRating = "down";
            if ( parentArticle.find('.dashicons-thumbs-up').hasClass('button_pressed') ) {
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
            getVotingScore(parentArticle, postID);
            if (cancelledVote) {
                // remove button_pressed class
                parentArticle.find(".dashicons-thumbs-" + selectedRating).removeClass("button_pressed");
            } else {
                parentArticle.find(".dashicons-thumbs-" + selectedRating).addClass(" button_pressed ");
            }

            if ( changedVote ) {
                if(selectedRating === "up") {
                    parentArticle.find(".dashicons-thumbs-down").removeClass("button_pressed");
                } else {
                    parentArticle.find(".dashicons-thumbs-up").removeClass("button_pressed");
                }
            }
        });

        request.fail(function( jqXHR, textStatus ) {
            console.log("status " + textStatus);
        });

    });

});


