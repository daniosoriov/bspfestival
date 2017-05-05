<?php
/* 
 * Remember that this file is only used if you have chosen to override event pages with formats in your event settings!
 * You can also override the single event page completely in any case (e.g. at a level where you can control sidebars etc.), as described here - http://codex.wordpress.org/Post_Types#Template_Files
 * Your file would be named single-event.php
 */
/*
 * This page displays a single event, called during the the_content filter if this is an event page.
 * You can override the default display settings pages by copying this file to yourthemefolder/plugins/events-manager/templates/ and modifying it however you need.
 * You can display events however you wish, there are a few variables made available to you:
 * 
 * $args - the args passed onto EM_Events::output() 
 */
global $EM_Event;

/*
echo $EM_Event->output(
  '[tabs tabdetails="Details" tabregister="Register" tabstandards="Standards"]
      [tab id=details]
          [one_half last="no"]<p>#_EVENTEXCERPT</p>[/one_half]
          [one_half last="yes"]<strong>Date/Time</strong><br/>Date - #_EVENTDATES<br/><i>#_EVENTTIMES</i>[/one_half]
          <h3>Details</h3>
          <div>#_EVENTNOTES</div>
          <h3>Location</h3>
          {has_location}
              [one_third last="no"]
                  <strong>Address</strong><br/>
                  #_LOCATIONADDRESS<br/>
                  #_LOCATIONTOWN<br/>
                  #_LOCATIONCOUNTRY<br/>
              [/one_third]
              [two_third last="yes"]
      <div id="map" style="float:right; margin:0px 0px 15px 15px;">#_MAP</div>
              [/two_third]
          {/has_location}
      [/tab]
      [tab id=register]
          <div>{has_bookings}#_BOOKINGFORM{/has_bookings}</div>
          {no_bookings}Online registion is unavailable for this event at the moment.{/no_bookings}
      [/tab]
      [tab id=standards]
          <h3>BHAA Standard Table</h3>
          <p>Like a golf handicap the BHAA standard table gives a runner a target time for the race distance</p>
          <p>#_BHAASTANDARDS</p>
      [/tab]
  [/tabs]');*/

echo $EM_Event->output(
  '
  <div class="event-section">
    <div class="col-xs-6 col-sm-6 col-md-6 col-lg-6">
      #_EVENTIMAGE
    </div>
    <div class="col-xs-6 col-sm-6 col-md-6 col-lg-6"></div>
    <div class="clearfix"></div>
  </div>
  
  <div class="event-section">
    <div class="col-xs-3 col-sm-3 col-md-3 col-lg-3">
      <p>
        <h6 style="text-align: left" class="vc_custom_heading">Date</h6>
        #j #M #Y #@_{ \u\n\t\i\l j M Y}
      </p>
      <p>
        <h6 style="text-align: left" class="vc_custom_heading">Time</h6>
        #_ATT{BSPFEventTime}<br />
        <a href="#_EVENTGCALURL" target="_blank" class="event-btn google-calendar-btn">Add to Google Calendar</a><br />
      </p>
      <h6 style="text-align: left" class="vc_custom_heading">Category</h6>
      #_CATEGORYNAME <!-- #_CATEGORIES -->
    </div>

    <div class="col-xs-3 col-sm-3 col-md-3 col-lg-3">
      <h6 style="text-align: left" class="vc_custom_heading">Location</h6>
      '. (($EM_Event->get_location()->location_name) ? '' : 'Exact location TBD<br />') .'
      {has_location}
      #_LOCATIONNAME<br />
      <!-- #_LOCATIONLINK<br /> -->
      #_LOCATIONADDRESS, #_LOCATIONTOWN, #_LOCATIONPOSTCODE, #_LOCATIONCOUNTRY<br />
      #_LOCATIONIMAGE
      {/has_location}
    </div>

    <div class="col-xs-1 col-sm-1 col-md-1 col-lg-1">
    </div>
    
    <div class="col-xs-5 col-sm-5 col-md-5 col-lg-5">
      {has_location}
      #_LOCATIONMAP
      {/has_location}
    </div>
    <div class="clearfix"></div>
  </div>
  
  <div class="event-section">
    <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
      <h6 style="text-align: left" class="vc_custom_heading">Description</h6>
      #_EVENTNOTES
    </div>
    <div class="clearfix"></div>
  </div>
  
  <div class="event-section">
    <div class="col-xs-6 col-sm-6 col-md-6 col-lg-6">
      <strong>Related category events</strong><br />
      #_CATEGORYALLEVENTS
    </div>
    <div class="col-xs-6 col-sm-6 col-md-6 col-lg-6">
      <strong>Related location events</strong><br />
      '. (($EM_Event->get_location()->location_name) ? '#_LOCATIONALLEVENTS' : 'Exact location TBD') .'
    </div>
    <div class="clearfix"></div>
  </div>
    
');

/* @var $EM_Event EM_Event */
//echo $EM_Event->output_single();
?>