/**
Crucible Plugin for Moodle
Copyright 2020 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL. CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Released under a GNU GPL 3.0-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.  Please see Copyright notice for non-US Government use and distribution.
This Software includes and/or makes use of the following Third-Party Software subject to its own license:
1. Moodle (https://docs.moodle.org/dev/License) Copyright 1999 Martin Dougiamas.
DM20-0196
 */

define(['jquery'], function($) {

    var timeout;

    return {
        init: function() {

            //console.log('access_token ' + access_token);
            //console.log('refresh_token ' + refresh_token);
            //console.log('scopes ' + scopes);
            //console.log('clientid ' + clientid);
            //console.log('clientsecret ' + clientsecret);
            //console.log('status ' + status);

            if (status == 'Active') {
                show_active();
            } else if (status == '') {
                show_ended();
            } else {
                show_wait();
            }
            run_loop();

            // todo implement this
            $(window).on("resize", function() {
                console.log('window resized');
                //setIFrameSize();
            });
        }
    };

    // todo make this work
    function get_refresh_token() {
        console.log('access_token ' + access_token);
        jQuery.ajax({
            // todo get proper url
            url: token_url,
            type: 'GET',
            contentType: 'application/json',
            dataType: 'json',
            beforeSend : function(xhr) {
                xhr.setRequestHeader('Authorization', 'Bearer ' + access_token);
            },
            success: function(response) {
                console.log(response);
            },
            error: function(response) {
                console.log(response);
            }
        });
        console.log('access_token ' + access_token);
   }

    function check_status() {

        if (id) {
            console.log('implementation id ' + id);
            jQuery.ajax({
                url: alloy_api_url + '/implementations/' + id,
                type: 'GET',
                contentType: 'application/json',
                dataType: 'json',
                beforeSend : function(xhr) {
                    xhr.setRequestHeader('Authorization', 'Bearer ' + access_token);
                },
                success: function(response) {
                    $.each(response, function(index, value) {
                        if (index == 'exerciseId') {
                            if (value) {
                                exerciseid = value;
                                console.log('exerciseid ' + exerciseid);
                             }
                        }
                        if (index == 'status') {
                            console.log('status ' + status);
                            if (value == 'Active') {
                                show_active();
                                clearTimeout(timeout);

                            //    if ((status != 'Active') && (value == 'Active')) {
                            //        //window.location.replace(window.location.href);
                            //        clearTimeout(timeout);
                            //    }
                            }
                            if ((value == 'Creating') || (value == 'Planning') || (value == 'Applying') || (value == 'Ending')) {
                                show_wait();
                            }
                            if (value == 'Ended') {
                                show_ended();
                                clearTimeout(timeout);
                            }
                            if (value == 'Failed') {
                                show_failed();
                                clearTimeout(timeout);
                            }
                            status = value;

                        }
                    });
                },
                error: function(response) {
                    if (response.status == '401') {
                        console.log('permission error, check token');
                        clearTimeout(timeout);
                        //get_refresh_token();
                    }
                }
            });
        } else {
            show_ended();
        }
    }

    function show_wait() {
        var x = document.getElementById('launch_button');
        x.style.display = 'none';
        var x = document.getElementById('end_button');
        x.style.display = 'none';
        var x = document.getElementById('wait');
        x.style.display = 'block';
        var x = document.getElementById('vm_or_link');
        x.style.display = 'none';
        var x = document.getElementById('failed');
        x.style.display = 'none';
    }

    function show_ended() {
        var x = document.getElementById('launch_button');
        x.style.display = 'block';
        var x = document.getElementById('end_button');
        x.style.display = 'none';
        var x = document.getElementById('wait');
        x.style.display = 'none';
        var x = document.getElementById('vm_or_link');
        x.style.display = 'none';
        var x = document.getElementById('failed');
        x.style.display = 'none';
    }

    function show_failed() {
        var x = document.getElementById('launch_button');
        x.style.display = 'none';
        var x = document.getElementById('end_button');
        x.style.display = 'none';
        var x = document.getElementById('wait');
        x.style.display = 'none';
        var x = document.getElementById('vm_or_link');
        x.style.display = 'none';
        var x = document.getElementById('failed');
        x.style.display = 'block';
    }

    function show_active() {
        var x = document.getElementById('launch_button');
        x.style.display = 'none';
        var x = document.getElementById('end_button');
        x.style.display = 'block';
        var x = document.getElementById('implementation');
        x.style.value = id;
        var x = document.getElementById('wait');
        x.style.display = 'none';
        var x = document.getElementById('vm_or_link');
        x.setAttribute('src', vm_app_url + '/exercises/' + exerciseid);
        x.style.display = 'block';
        // todo only set the sorrect attribute
        var x = document.getElementById('vm_or_link');
        x.setAttribute('href', player_app_url + '/exercise-player/' + exerciseid);
        x.style.display = 'block';
        var x = document.getElementById('failed');
        x.style.display = 'none';
    }

    function run_loop() {
        timeout = setTimeout(function() {
            check_status();
            run_loop();
            if (status == 'Ended') {
                console.log('lab has ended');
            }
        }, 5000);
    }

    // todo make this work
    function setIFrameSize() {
        var ogWidth = 700;
        var ogHeight = 600;
        var ogRatio = ogWidth / ogHeight;

        var windowWidth = $(window).width();
        if (windowWidth < 480) {
            var parentDivWidth = $(".iframe-class").parent().width();
            var newHeight = (parentDivWidth / ogRatio);
            $(".iframe-class").addClass("iframe-class-resize");
            $(".iframe-class-resize").css("width", parentDivWidth);
            $(".iframe-class-resize").css("height", newHeight);
        } else {
            // $(".iframe-class-resize").removeAttr("width");
            // $(".iframe-class-resize").removeAttr("height");
            $(".iframe-class").removeClass("iframe-class-resize");
        }
        console.log('iframe resized');
    }

});

