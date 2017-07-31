/*
 * javascript compressor: https://jscompress.com/
 */
jQuery(document).ready(function ($) {
    if (document.getElementsByClassName("pop")) {
        $('#ModalImage').modal('show');
        $('.pop').on('click', function () {
            //$('.image-preview').attr('src', $(this).find('img').attr('src'));
            $('#ModalImage').modal('show');
        });
    }
    $('#ModalImage').on('hidden.bs.modal', function (e) {
        updateVotes();
    });

    var $gallery = $('.bspf-gallery-wrapper');

    $gallery.lightGallery({
        selector: '.img-wrapper',
        mousewheel: false,
        download: false,
        thumbnail: false,
        autoplay: false,
        fullScreen: false,
        zoom: false,
        googlePlus: false,
        pinterest: false,
    });

    $gallery.on('onCloseAfter.lg', function (event) {
        updateVotes();
    });

    $gallery.on('onAfterAppendSubHtml.lg', function (event, index) {
        // Check the photo voting status.
        var star = $('.lg-sub-html').find('.star-bspf-public');
        $(star).removeClass('fa-star fa-star-o').addClass('fa-spinner fa-spin');
        if (!$(star).hasClass('checked')) {
            var data = {
                _ajax_nonce: bspf_ajax.nonce,
                action: 'BSPFAjaxGetVote',
                pid: $(star).attr('data-pid'),
            }
            $.get(bspf_ajax.ajax_url, data, function (response) {
                if ($(star).hasClass('checked')) return;
                if (response.is_voted === true) {
                    $(star).removeClass('fa-spinner fa-spin').addClass('fa-star checked').attr('title', 'Favorite!');
                }
                else {
                    $(star).removeClass('fa-spinner fa-spin').addClass('fa-star-o checked').attr('title', 'Make favorite!');
                }
            }, "json");
        }

        // Allow people to vote from the lightGallery.
        $(".star-bspf-public").click(function () {
            var vote = ($(this).hasClass('fa-star-o')) ? 5 : 0;
            votePid($(this), $(this).attr('data-pid'), vote, 'public');
        });
    });

    // Events once each photo is loaded.
    $gallery.on('onSlideItemLoad.lg', function (event, index) {
        var star = $('.lg-sub-html').find('.star-bspf-public');
        // Change the Facebook sharing URL.
        // Facebook tool to create URLs: https://apps.lazza.dk/facebook/
        var facebookURL = 'https://www.facebook.com/sharer/sharer.php?' +
            'u=' + encodeURIComponent($(star).attr('data-url')) +
            '&picture=' + encodeURIComponent($('.lg-image:visible').attr('src')) +
            '&title=' + encodeURIComponent($('.lg-sub-html').text() + ' - BSPF Social Media Voting') +
            '&caption=' + encodeURIComponent('bspfestival.org') +
            '&description=' + encodeURIComponent('Brussels Street Photography Festival contest submission entry. Vote on your favorite photo and help the photographer win the Social Media Prize.');
        var twitterURL = 'https://twitter.com/intent/tweet?' +
            'text=Vote for ' + encodeURIComponent($(star).attr('data-name')) + ' on the BSPF Contest!' +
            '&url=' + encodeURIComponent($(star).attr('data-url')) +
            '&hashtags=StreetPhotography,BSPF2017' +
            '&via=BSPFestival_Off';
        $('#lg-share-facebook').attr('href', facebookURL);
        $('#lg-share-twitter').attr('href', twitterURL);
    });

    $(".img-bspf-private").click(function () {
        $(this).toggleClass("img-bspf-selected");
        totalSelected = $(".img-bspf-selected").length;
        console.log('selected photos: ' + totalSelected);
        $(".selected-photos").text(totalSelected);
        if (totalSelected > 0) {
            $(".bspf-toolbar").show();
        }
        else {
            $(".bspf-toolbar").hide();
        }
    });

    $(".star-bspf-toolbar").click(function () {
        $(".star-bspf-toolbar").removeClass("selected");

        $(this).removeClass("fa-star-o").addClass("selected fa-star").delay(500).queue(function () {
            $(this).removeClass("selected fa-star").addClass("fa-star-o").dequeue();
        });
        var vote_num = getVoteNumber($(this).parent().attr("class"));
        $(".img-bspf-selected").each(function (index) {
            $(this).parent().find("ul").children().each(function (index) {
                updateVoteStars($(this).children(), index, vote_num);
            });
        });
        ;
    });

    $(".star-bspf-private").click(function () {
        var vote = $(this).parent().attr("class");
        var vote_num = getVoteNumber(vote);
        var last_vote = $(this).parent().parent().find(".fa-star:last").parent().attr("class");
        var pid = $(this).parent().parent().attr('data-pid');
        // Assigning a vote, change the stars.
        if (vote != last_vote) {
            $(this).addClass("fa-star").removeClass("fa-star-o");
            $(this).parent().parent().children().each(function (index) {
                updateVoteStars($(this).children(), index, vote_num);
            });
        }
        // If deselecting the star, so removing the vote.
        else if (vote == last_vote) {
            $(this).parent().parent().children().each(function (index) {
                $(this).children().addClass("fa-star-o").removeClass("fa-star").attr('title', 'Vote ' + (index + 1));
            });
            vote_num = 0;
        }
        votePid($(this), pid, vote_num, 'private');
    });

    // Scale slider system in bootstrap & js: http://seiyria.com/bootstrap-slider/

    // REST API example with WordPress: https://deliciousbrains.com/using-wp-rest-api-wordpress-4-4/
    // Check the nonce on the backend: https://viastudio.com/wordpress-rest-api-secure-ajax-calls-custom-endpoints/

    /*if ($(this).hasClass('fa-spinner')) return;
     var this2 = this;
     var pid = $(this).attr('data-pid');
     var method = ($(this).hasClass('fa-star-o')) ? 'PUT' : 'DELETE';
     $(this).removeClass('fa-star fa-star-o').addClass('fa-spinner fa-spin');

     $.ajax({
     url: wpApiSettings.root + 'bspfestival/v1/image/' + pid,
     method: method,
     beforeSend: function (xhr) {
     xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
     },
     // data: {
     //     'title': 'Hello Moon'
     // }
     }).done(function (response) {
     console.log(response);
     }).fail(function (jqXHR, textStatus) {
     console.log("Request failed: " + textStatus);
     });*/

    $(".star-bspf-public").click(function () {
        var vote = ($(this).hasClass('fa-star-o')) ? 5 : 0;
        votePid($(this), $(this).attr('data-pid'), vote, 'public');
    });

    $('.facebook-share').on('click', function () {
        FBshare($(this).attr('data-url'));
    })

    function FBshare(url) {
        FB.ui({
            method: 'feed',
            link: url,
        }, function (response) {
            // Debug response (optional)
        });
    }

    /**
     * Vote on a picture.
     * @param element mixed the element that triggered the event, an icon.
     * @param pid integer the pid of the picture.
     * @param vote integer the vote for the picture.
     * @param type string public or private.
     */
    function votePid(element, pid, vote, type) {
        if (element.hasClass('fa-spinner')) return;
        var data = {
            _ajax_nonce: bspf_ajax.nonce,
            action: 'BSPFAjaxVoting',
            pid: pid,
            vote: vote,
        }
        console.log(data);
        element.removeClass('fa-star fa-star-o').addClass('fa-spinner fa-spin');
        $.post(bspf_ajax.ajax_url, data, function (response) {
            console.log(response);
            if (response) {
                if (vote > 0) {
                    var title = (type == 'public') ? 'Favorite!' : 'Remove vote';
                    element.removeClass('fa-spinner fa-spin').addClass('fa-star').attr('title', title);
                }
                else {
                    var title = (type == 'public') ? 'Make favorite!' : 'Vote ' + vote;
                    element.removeClass('fa-spinner fa-spin').addClass('fa-star-o').attr('title', title);
                }
            }
        }, "json");
    }

    function getVoteNumber(vote) {
        var obj = {one: 1, two: 2, three: 3, four: 4, five: 5};
        return obj[vote];
    }

    function updateVoteStars(element, index, vote) {
        element.addClass("fa-star-o").removeClass("fa-star").attr('title', 'Vote ' + (index + 1));
        if ((index + 1) <= vote) {
            element.addClass("fa-star").removeClass("fa-star-o");
            if ((index + 1) == vote) {
                element.attr('title', 'Remove vote');
            }
        }
    }

    function updateVotes() {
        // Update the votes made while using lightGallery.
        $('#myModal').modal('show');
        var data = {
            _ajax_nonce: bspf_ajax.nonce,
            action: 'BSPFAjaxGetVote',
        }
        $.get(bspf_ajax.ajax_url, data, function (response) {
            $('.star-bspf-public').removeClass('fa-star').addClass('fa-star-o').attr('title', 'Make favorite!');
            for (var count in response.voted) {
                $(".star-bspf-public[data-pid='" + response.voted[count] + "']").removeClass('fa-star-o').addClass('fa-star').attr('title', 'Favorite!');
            }
            $('#myModal').modal('hide');
        }, "json");
    }
});