jQuery(document).ready(function($) {
  // Single Contests Curation gallery
  if (document.getElementsByClassName("image-wrapper-bspf")) {
    $("img").attr("title", "");
    $("img").attr("alt", "");
    
    $.each(document.cookie.split(/; */), function()  {
      var splitCookie = this.split('=');

      var string = splitCookie[0],
      substring = "nggv_vote_";
      if (string.indexOf(substring) !== -1) {
        delete_cookie(splitCookie[0]);
        console.log('deleted cookie: '+ splitCookie[0]);
      }
    });
    
    window.setInterval(function(){
      delete_cookies();
    }, 5000);

    function delete_cookies() {
      $('.image-wrapper-bspf').each(function() {
        var name = 'nggv_vote_' + $(this).attr('id') + '_0';
        if (document.cookie.indexOf(name) >= 0) {
          delete_cookie(name);
        }
      });
    }
    
    function delete_cookie(name) {
      document.cookie = name + '=;path=/;expires=Thu, 01 Jan 1970 00:00:01 GMT;';
      console.log('cookie deleted: '+name);
    }
    
    function getParameterByName(name, url) {
      name = name.replace(/[\[\]]/g, "\\$&");
      var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
          results = regex.exec(url);
      if (!results) return null;
      if (!results[2]) return '';
      return decodeURIComponent(results[2].replace(/\+/g, " "));
    }
  }
  
  // Series Contests Curation gallery
  if (window.location.href.indexOf("series-contest-curation") > -1) {
    if (document.getElementsByClassName("galleria-thumbnails-container")) {
      window.setInterval(function(){
        hide_names();
      }, 200);

      function hide_names() {
        $('.galleria-info:visible').hide();
        $('.galleria-dock-toggle-container:visible').hide();
        $('.nggpl-toolbar-button-info:visible').hide();
      }
    }
  }
});