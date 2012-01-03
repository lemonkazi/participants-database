jQuery(document).ready(function($){

  // flag the row as changed
  $('table.manage-fields input, table.manage-fields textarea, table.manage-fields select').change(function(el) {
    var matches = $(this).attr('name').match(/row_(\d+)\[/);
    $('#status_'+matches[1]).attr('value','changed');
  });

  // defeat return key submit behavior
  $("form").bind("keypress", function(e) {
    if (e.keyCode == 13) return false;
  });

  // disable autocomplete
  if ($.browser.mozilla)
    $("form").attr("autocomplete", "off");

  // set up the UI tabs
  $("#fields-tabs").tabs( {
                            fx: { opacity: "show", duration: "fast" },
                            cookie: { expires:1 }
                          });

  // set up the delete functionality

  // set up the click function
  $('a.delete').click(function (e) { // if a user clicks on the "delete" image

    //prevent the default browser behavior when clicking
    e.preventDefault();

    var row_id = $(this).attr('name').replace( /^delete_/,'');
    var parent = $(this).parent().parent('tr');
    var name = parent.find( 'td.title input' ).attr('value');
    var thing = $(this).attr('ref');
    var count = $(this).attr('href');
    // test to see if the group we're deleting has fields in it
    var not_empty_group = ( /[0-9]+/.test( count ) && count > 0 ) ? true : false ;

    // set the dialog text
    if ( not_empty_group ) {

      $('#confirmation-dialog').html('<h4>You must remove all fields from the "'+ name + '" group before deleting it.</h4>');

      // initialize the dialog action
      $('#confirmation-dialog').dialog({
        autoOpen: false,
        height: 'auto',
        minHeight: '20',
        buttons: {
          "Ok": function () { //If the user choose to click on "OK" Button
            $(this).dialog('close'); // Close the Confirmation Box
          }// ok
        } // buttons
      });// dialog

    } else {

      $('#confirmation-dialog').html('<h4>Delete the "'+ name + '" ' + thing + '?</h4>');

      // initialize the dialog action
      $('#confirmation-dialog').dialog({
        autoOpen: false,
        height: 'auto',
        minHeight: '20',
        buttons: {
          "Ok": function () { //If the user choose to click on "OK" Button
              $(this).dialog('close'); // Close the Confirmation Box
              $.ajax({ //make the Ajax Request
                type: 'post',
                url: window.location.pathname + window.location.search,
                data: 'delete=' + row_id + '&action=delete_' + thing,
                beforeSend: function () {
                  parent.animate({
                    'backgroundColor': 'yellow'
                  }, 600);
                },
                success: function (response) {
                  parent.slideUp(600, function () { //remove the Table row .
                      parent.remove();
                  });
                }
              });// ajax
          },// ok
          "Cancel": function () { //if the User Clicks the button "cancel"
            $(this).dialog('close');
          } // cancel
        } // buttons
      });// dialog
    }
    $('#confirmation-dialog').dialog('open'); //Display confirmation dialog when user clicks on "delete Image"
    return false;
  }); // click function
}); // document ready

// prevents collapse of table row while dragging
var fixHelper = function(e, ui) {
    ui.children().each(function() {
        jQuery(this).width(jQuery(this).width());
    });
    return ui;
};
// grabs the id's of the anchor tags and puts them in
// a string for the ajax reorder functionality
function serializeList(container)
{
  var str = '';
  var n = 0;
  var els = container.find('a');
  for (var i = 0; i < els.length; ++i) {
    var el = els[i];
    var p = el.id.lastIndexOf('_');
    if (p != -1) {
      if (str != '') str = str + '&';
			str = str + el.id + '=' + n;
      ++n;
    }
  }
  return str;
}