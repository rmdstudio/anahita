/**
 * Author: Rastin Mehr
 * Email: rastin@anahitapolis.com
 * Copyright 2015 rmdStudio Inc. www.rmdStudio.com
 * License: GPL3
 */

;(function($, window) {
    
    'use strict';
    
    $.widget('anahita.paginator',{
        
        options: {
          scrollToTop: null,
          limit: null
        },
        
        _getCreateOptions: function() {
            return this.element.data('paginationOptions');
        },
       
        _create: function() {
            this._on(this.element, {
                "click a": function(event) {
                    event.preventDefault();
                    var li = $(event.target).parent();
                    
                    if(!li.hasClass('disabled') && !li.hasClass('active')) {
                        this._getPage(event);
                    } 
                }
            })
        },
        
        _getPage: function(event) {
            var a = $(event.target); 
            var self = this;
//            var ul = this.element.find('ul');
            
//            ul.addClass('uiActivityIndicator');
            
            $.ajax({
              url: a.attr('href')
            })
            .done(function(response){
                
                self._updateHash(a.attr('href'));
                
                var entities = $(response).find('.an-entities');
                var pagination = $(response).find('.pagination');
                
                pagination.paginator();

                if(self.options.scrollToTop) {
                    $('html').animate({scrollTop:'0px'},'slow');
                }

                $('.an-entities').fadeOut('fast', function() {
                    $(this).fadeIn('fast',function() {
                        $(this).replaceWith(entities);
                    });
                });

                $('.pagination').fadeOut('fast', function() {
                    $(this).fadeIn('fast',function() {
                        $(this).replaceWith(pagination);
                    });
                });

//                ul.removeClass('uiActivityIndicator');
            });
        },
        
        _updateHash: function(url) {
            var hash = url.split('?')
            window.location.hash = hash[1];
        }
        
    });
    
    $('div[data-behavior=pagination]').paginator();
    
}(jQuery, window));