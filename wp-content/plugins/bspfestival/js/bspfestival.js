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

    function BSPFPrivateSeriesLightGallery() {
        var $series = $('.series-wrapper');

        $series.lightGallery({
            selector: '.series-img',
            mousewheel: false,
            download: false,
            thumbnail: false,
            autoplay: false,
            fullScreen: false,
            zoom: false,
            facebook: false,
            twitter: false,
            googlePlus: false,
            pinterest: false,
            loop: false,
        });
    }


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

    $(".icon-bspf-filter").click(function () {
        var element = $("#bspf-filter");
        var prev_filter = element.attr("data-filter");
        var new_filter = $(this).attr("data-filter");
        var caption = $(this).attr("title");

        // Update the other filters.
        $(".icon-bspf-filter").each(function () {
            var ele_fa = $(this).attr("data-fa");
            var ele_filter = $(this).attr("data-filter");
            $(this).addClass(ele_fa + "-o").removeClass(ele_fa);
            if (new_filter != prev_filter) {
                if (ele_filter > 0 && ele_filter <= new_filter) {
                    $(this).addClass(ele_fa).removeClass(ele_fa + "-o");
                }
                else if (ele_filter < 0 && ele_filter == new_filter) {
                    $(this).addClass(ele_fa).removeClass(ele_fa + "-o");
                }
            }
        });
        if (new_filter == prev_filter) {
            new_filter = 0;
            caption = 'Non voted';
        }
        element.attr("data-filter", new_filter);
        element.attr("data-caption", caption);
    });

    $(".bspf-filter-button").click(function () {
        var pos = $(this).attr('data-position');
        BSPFPrivateFilter(pos, 1);
    });

    BSPFPrivateVoteAction();
    BSPFPrivateChangePage();
    BSPFPrivateSeriesLightGallery();

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
        element.removeClass('fa-star fa-star-o').addClass('fa-spinner fa-spin');
        $.post(bspf_ajax.ajax_url, data, function (response) {
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

    function BSPFPrivateVoteAction() {
        $(".icon-bspf-private").click(function () {
            BSPFUpdateIcons($(this), 'pid');
        });
        $(".vote-series").click(function () {
            BSPFUpdateIcons($(this), 'gid');
        });
    }

    function BSPFUpdateIcons(t, group) {
        var vote = t.attr("data-vote");
        var element = t.parent().parent();
        var last_vote = element.attr("data-vote");
        var category = element.attr("data-category");
        var groupId = (group == 'pid') ? element.attr('data-pid') : element.attr('data-gid');
        var fa = t.attr("data-fa");

        // Update the icons.
        // Assigning a vote, change the icons.
        element.children().each(function () {
            var ele = $(this).find("i");
            var ele_vote = ele.attr("data-vote");
            var ele_fa = ele.attr("data-fa");
            var sel = ele.attr("data-sel");
            var unsel = ele.attr("data-unsel");
            if (ele_vote != undefined) {
                ele.addClass(ele_fa + "-o").removeClass(ele_fa).attr("title", unsel);
                if (vote != last_vote) {
                    if (ele_vote > 0 && ele_vote < vote) {
                        ele.addClass(ele_fa).removeClass(ele_fa + "-o").attr("title", unsel);
                    }
                    else if (ele_vote > 0 && ele_vote == vote) {
                        ele.addClass(ele_fa).removeClass(ele_fa + "-o").attr("title", sel);
                    }
                    else if (ele_vote < 0 && ele_vote == vote) {
                        ele.addClass(ele_fa).removeClass(ele_fa + "-o").attr("title", sel);
                    }
                }
            }
        });
        // Removing a vote.
        if (vote == last_vote) {
            t.addClass(fa + "-o").removeClass(fa).attr("title", t.attr("data-unsel"));
            vote = 0;
        }

        // Update the last voted.
        element.attr("data-vote", vote);

        votePrivate(groupId, vote, element, group, category);
    }

    function BSPFPrivateFilter(pos, page) {
        var element = $("#bspf-filter");
        var filter = element.attr("data-filter");
        var caption = element.attr("data-caption");
        var category = element.attr("data-category");
        var group = element.attr("data-group");
        var gid = element.attr("data-gid");
        var data = {
            _ajax_nonce: bspf_ajax.nonce,
            action: 'BSPFAjaxFilter',
            filter: filter,
            category: category,
            group: group,
            gid: gid,
            page: page,
        }
        $(".bspf-filter-text").html('<i class="fa fa-spinner fa-spin" aria-hidden="true"></i>');
        $(".bspf-filter-current-page").html('<i class="fa fa-spinner fa-spin" aria-hidden="true"></i>');

        $.get(bspf_ajax.ajax_url, data, function (response) {
            $(".bspf-filter-text").html(response.text);
            $(".bspf-filter-pages").html(response.pages);
            $(".bspf-gallery-ajax").html(response.content);
        }, "json")
            .done(function () {
                if (pos == 'down') {
                    $('html, body').animate({scrollTop: $(".bspf-gallery-ajax").offset().top - 30}, 'slow');
                }
                BSPFPrivateVoteAction();
                BSPFPrivateChangePage();
                BSPFPrivateSeriesLightGallery();
            });
    }

    function BSPFPrivateChangePage() {
        $(".bspf-filter-page").click(function () {
            var pos = $(this).parent().parent().attr('data-position');
            BSPFPrivateFilter(pos, $(this).attr("data-page"));
        });
    }

    function votePrivate(groupId, vote, element, group, category) {
        var data = {
            _ajax_nonce: bspf_ajax.nonce,
            action: 'BSPFAjaxVoting',
            group_id: groupId,
            vote: vote,
            private: true,
            group: group,
            category: category,
        }
        var scroll = element.attr("data-scroll-num");
        var last_scroll = parseInt($("div[data-scroll]:last").attr("data-scroll")) + 1;
        var scroll_to = (scroll == last_scroll) ? $("div[data-scroll-filter]") : $("div[data-scroll='" + scroll + "']");

        element.parent().find(".alert").remove();
        element.hide().parent().append('<i class="fa fa-spinner fa-spin"></i>');
        scrollToAnchor(scroll_to);

        $.post(bspf_ajax.ajax_url, data, function (response) {
            if (response.status == 'success') {
                element.show().parent().find(".fa-spinner").remove();
                element.parent().find(".alert").remove();
                if (response.values) {
                    for (var v in response.values.stats) {
                        $(".vote-" + v).html(response.values.stats[v]);
                    }
                }
            }
            else {
                var message = '<div class="alert alert-danger alert-dismissable"> <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a> <strong>Error!</strong> ' + response.message + '</div>';
                element.show().parent().find(".fa-spinner").remove();
                element.parent().find(".alert").remove()
                element.parent().append(message);
            }
        }, "json");
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

    function scrollToAnchor(element) {
        if (element.length) {
            $('html, body').animate({scrollTop: element.offset().top - 30}, 'slow');
        }
    }

});