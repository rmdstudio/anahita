/**
 * Author: Rastin Mehr
 * Email: rastin@anahitapolis.com
 * Copyright 2015 rmdStudio Inc. www.rmdStudio.com
 * License: GPL3
 */

;(function ($, window, document) {
	
    'use strict';
    
    $.fn.notificationsCounter = function () {
    	
    	var title = $('title');
    	var metaTitle = title.html();
    	var counter = $(this);
    	
    	function pulse() {
    		
    		$.ajax({
                
    			headers: { 
                    accept: 'application/json'
                },
                
                url : counter.data('url')
            
    		}).done(function (data) {
                
    			counter.html(data.new_notifications);
    			
    			 if (data.new_notifications > 0) {
                     
    				 title.html('(' + data.new_notifications + ') ' + metaTitle);
                     counter.addClass('counter-important');
                     
                 } else {
                	 
                     title.html(metaTitle);
                     counter.removeClass('counter-important');
                 }
                
                setTimeout(pulse, counter.data('interval'));
            })
    	};
    	
    	pulse();
    };
    
    //counter
    $('#notifications-counter').notificationsCounter();
    
    //popover
    $('body').on('click', 'a[data-trigger*="notifications-popover"]', function ( event ) {
    	
    	event.preventDefault();
    	
    	var elem = $(this);
    	
    	$.get(elem.attr('href'), function (response){

    		var notifications = $(response);
    		var title = notifications.filter('.popover-title').html();
    		var content = notifications.filter('.popover-content').html();
    		
    		elem.popover({
    			title : title,
    			content: content,
    			html : true,
    			placement: 'bottom'
    		}).popover('show');
    	});
    });
	
}(jQuery, window, document));