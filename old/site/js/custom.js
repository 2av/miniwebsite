/*jslint browser: true*/
/*global $, jQuery, Modernizr, google, _gat*/
/*jshint strict: true */

/*************** GOOGLE ANALYTICS ***********/

/*************** REPLACE WITH YOUR OWN UA NUMBER ***********/
window.onload = function () { "use strict"; gaSSDSLoad("UA-39305352-1"); }; //load after page onload
/*************** REPLACE WITH YOUR OWN UA NUMBER ***********/


var isMobile = false;
var isDesktop = false;


$(window).on("load resize",function(e){



		//mobile detection
		if(Modernizr.mq('only all and (max-width: 767px)') ) {
			isMobile = true;
		}else{
			isMobile = false;
		}


		//tablette and mobile detection
		if(Modernizr.mq('only all and (max-width: 1024px)') ) {
			isDesktop = false;
		}else{
			isDesktop = true;
		}
		//toTop(isMobile);
	});

//RESIZE EVENTS
$(window).resize(function () { 

	Modernizr.addTest('ipad', function () {
		return !!navigator.userAgent.match(/iPad/i);
	});
	
	if (!Modernizr.ipad) {  
		initializeMainMenu(); 
	}

	/* Reload the page to make sure everything fits in its media query*/
	//window.location.href = window.location.href;
});

/*
|--------------------------------------------------------------------------
| DOCUMENT READY
|--------------------------------------------------------------------------
*/  
$(document).ready(function() {


	"use strict";
	$(this).scrollTop(0);

	/** INIT FUNCTIONS **/
	initializeMainMenu();

	if( $('.portfolioMosaic').length ){
		moreLinkMosaicPorfolio('<h2>More projects</h2>', 'portfolio-3columns.html', 'star');
	}
	

   /**
    |--------------------------------------------------------------------------
    |  fullwidth image
    |--------------------------------------------------------------------------
    */
    /** FULLSCREEN IMAGE **/

    $(window).on("resize",function(e){
    	if ($('#homeFullScreen').length){ fullscreenImage(); }
    });

    if ($('#homeFullScreen').length){ fullscreenImage();  }


	/*
	|--------------------------------------------------------------------------
	| Menu One page
	|--------------------------------------------------------------------------
	*/

	if ($('#onePage').length){


		$('#resMainMenu .nav a').on('click', function(){
			if($(".navbar-toggle").css('display') == 'block' ){
				$(".navbar-toggle").click();
			}

		});

		$('#mainHeader .nav li a, .scrollLink').bind('click', function(event) {
			var $anchor = $(this);
			var headerH = $('.navbar-header').outerHeight() -1;

			$('html, body').stop().animate({
				scrollTop : $($anchor.attr('href')).offset().top - headerH + "px"
			}, 1200, 'easeInOutExpo');

			event.preventDefault();
		});



	}


	/*
	|--------------------------------------------------------------------------
	| Menu Fixed (multipage)
	|--------------------------------------------------------------------------
	*/

	$(window).on('scroll.nekofixedmenu', function () {

		if( $(window).width() > 1025 ){

			if( $(window).scrollTop() < 100 ){
				$('#mainHeader').removeClass('fixedHeader');
				$('#mainHeader').css({
					'display': 'block',
					'margin-top': '0'
				});
			}else{
				if( !$('#mainHeader').hasClass('fixedHeader') ){
					$('#mainHeader').animate(
						{'margin-top': '-400px'},
						0, function() {
							$('#mainHeader').addClass('fixedHeader');

							$('#mainHeader').animate(
								{'margin-top': '0'},
								300, 'easeInOutCirc',function() {
							});
					});	
				}
			}
		}
	});	

	/*	
	if( $(window).scrollTop() < 100 ){
		$('#mainHeader').removeClass('fixedHeader');
	}else{
		$('#mainHeader').addClass('fixedHeader');
	}
	*/
	//$(window).trigger('scroll.nekofixedmenu');



	 /*
    |--------------------------------------------------------------------------
    |  form placeholder for IE
    |--------------------------------------------------------------------------
    */
    if(!Modernizr.input.placeholder){

    	$('[placeholder]').focus(function() {
    		var input = $(this);
    		if (input.val() == input.attr('placeholder')) {
    			input.val('');
    			input.removeClass('placeholder');
    		}
    	}).blur(function() {
    		var input = $(this);
    		if (input.val() == '' || input.val() == input.attr('placeholder')) {
    			input.addClass('placeholder');
    			input.val(input.attr('placeholder'));
    		}
    	}).blur();
    	$('[placeholder]').parents('form').submit(function() {
    		$(this).find('[placeholder]').each(function() {
    			var input = $(this);
    			if (input.val() == input.attr('placeholder')) {
    				input.val('');
    			}
    		})
    	});

    }	

    /*
    |--------------------------------------------------------------------------
    | MAGNIFIC POPUP
    |--------------------------------------------------------------------------
    */


    if( $("a.image-link").length){

    	$("a.image-link").click(function (e) {

    		var items = [];

    		items.push( { src: $(this).attr('href')  } );

    		if($(this).data('gallery')){

    			var $arraySrc = $(this).data('gallery').split(',');

    			$.each( $arraySrc, function( i, v ){
    				items.push( {
    					src: v 
    				});
    			});     
    		}



    		$.magnificPopup.open({
    			type:'image',
    			mainClass: 'mfp-fade',
    			items:items,
    			gallery: {
    				enabled: true 
    			}
    		});

    		e.preventDefault();
    	});

    }


    // iframe
    if( $("a.image-iframe").length){
    	$('a.image-iframe').magnificPopup({type:'iframe',mainClass: 'mfp-fade'});
    }

    // popup
    if( $("a.open-popup-link").length){
    	$('.open-popup-link').magnificPopup({
    		type:'inline',
    		midClick: true 
    	});
    }
    /*
    |--------------------------------------------------------------------------
    | TOOLTIP
    |--------------------------------------------------------------------------
    */

    $('.tips').tooltip({placement:'auto'});

    
    
    /*
    |--------------------------------------------------------------------------
    | COLLAPSE
    |--------------------------------------------------------------------------
    */

    $('.accordion').on('show hide', function(e){
    	$('.accordion-toggle').removeClass('active');
    	$(e.target).siblings('.accordion-heading').find('.accordion-toggle').addClass('active');
    	$(e.target).siblings('.accordion-heading').find('.accordion-toggle i').toggleClass('icon-plus icon-minus', 200);

    });

    /*
    |--------------------------------------------------------------------------
    | CONTACT
    |--------------------------------------------------------------------------
    */   
    $('.slideContact').click(function(e){

    	if ( $(window).width() >= 800){

    		$('#contact').slideToggle('normal', 'easeInQuad',function(){

    			$('#contactinfoWrapper').css('margin-left', 0);
    			$('#mapSlideWrapper').css('margin-left', 3000);
    			$('#contactinfoWrapper').fadeToggle();


    		});
    		$('#closeContact').fadeToggle(); 
    		return false;

    	}else{

    		return true;

    	}
    	e.preventDefault();
    });
    
    
    $('#closeContact').click(function(e){


    	$('#contactinfoWrapper').fadeOut('normal', 'easeInQuad',function(){
    		$('#contactinfoWrapper').css('margin-left', 0);
    		$('#mapSlideWrapper').css('margin-left', 3000);
    	});

    	$('#contact').slideUp('normal', 'easeOutQuad');

    	$(this).fadeOut();

    	e.preventDefault();

    });
    

    
    /* MAP */
    $('#mapTrigger').click(function(e){


    	$('#mapSlideWrapper').css('display', 'block');
    	initialize('mapWrapper');

    	$('#contactinfoWrapper, #contactinfoWrapperPage').animate({
    		marginLeft:'-2000px' 
    	}, 400, function() {}); 


    	$('#mapSlideWrapper').animate({
    		marginLeft:'25px' 
    	}, 400, function() {});  

    	appendBootstrap();

    	e.preventDefault();
    });



    appendBootstrap();
    
    
    
    $('#mapTriggerLoader').click(function(e){


    	$('#mapSlide').css('display', 'block');

    	$('#contactSlide').animate({
    		marginLeft:'-2000px' 
    	}, 400, function() {}); 


    	$('#mapSlide').animate({
    		marginLeft:'0' 
    	}, 400, function() {
    		$('#contactSlide').css('display', 'none');
    	});  


    	appendBootstrap();

    	e.preventDefault();
    });
    
    
    $('#mapReturn').click(function(e){
        //$('#mapWrapper').css('margin-bottom', '3em');
        
        $('#contactSlide').css('display', 'block');
        $('#mapSlide').animate({
        	marginLeft:'3000px' 
        }, 400, function() {});       
        

        $('#contactSlide').animate({
        	marginLeft:'0' 
        }, 400, function() {
        	$('#mapSlide').css('display', 'none');
        }); 

        e.preventDefault();
    }); 

    /*
    |--------------------------------------------------------------------------
    | OWL CAROUSEL
    |--------------------------------------------------------------------------
    */

    if($('.nekoDataOwl, .neko-data-owl').length){

    	$( '.nekoDataOwl, .neko-data-owl' ).each(function( index ) {
    		var $this = $(this);
    		$this.owlCarousel(
    		{
    			items:$this.data('neko_items'),
    			navigation:$this.data('neko_navigation'),
    			singleItem:$this.data('neko_singleitem'),
    			autoPlay:$this.data('neko_autoplay'),
    			itemsScaleUp:$this.data('neko_itemsscaleup'),
    			navigationText:['<i class="icon-left-open"></i>','<i class="icon-right-open"></i>'], 
    			pagination:$this.data('neko_pagination'),
    			paginationNumbers:$this.data('neko_paginationnumbers'),
    			autoHeight:$this.data('neko_autoheight'),
    			mouseDrag:$this.data('neko_mousedrag'),
    			transitionStyle:$this.data('neko_transitionstyle'),
    			responsive:true,
    			lazyLoad : true,
    			addClassActive:true,
    			itemsDesktop :[1450,4],
    			afterInit: function(){

    				if( Modernizr.mq('only all and (min-width: 769px)') ){
    					$('.caption-wrapper').css('display', 'none');
    					$('.active .caption-wrapper').css('display', 'block');
    					$('.active .caption-content-position', $this).children().each(function(index, val) {
    						$(this).addClass('animated '+$(this).data('neko_animation'));
    					});
    				}else{
    					$('.caption-wrapper').css('display', 'block');
    				}



    			},
    			beforeMove:  function(){

    				if( Modernizr.mq('only all and (min-width: 769px)') ){

    					$('.caption-wrapper').css('display', 'none');
    					$('.active .caption-wrapper').css('display', 'block');
    					$('.caption-content-position').children().each(function(index, val) {
    						$(this).removeClass('animated '+$(this).data('neko_animation'));
    					});

    				}else{
    					$('.caption-wrapper').css('display', 'block');
    				}

    			},
    			afterMove:  function(){

    				if( Modernizr.mq('only all and (min-width: 769px)') ){
    					$('.caption-wrapper').css('display', 'none');
    					$('.active .caption-wrapper').css('display', 'block');
    					$('.active .caption-content-position', $this).children().each(function(index, val) {

    						$(this).addClass('animated '+$(this).data('neko_animation'));
    					});

    				}else{
    					$('.caption-wrapper').css('display', 'block');
    				}
    			}
    		});
});
}



    /*
    |--------------------------------------------------------------------------
    | REVOLUTION SLIDER
    |--------------------------------------------------------------------------
    */ 
    if($('#rsDemoWrapper').length){


    	$('.tp-banner').revolution(
    	{
    		delay:9000,
    		startwidth:1170,
    		startheight:500,
    		hideThumbs:10,
    		fullWidth:"on",
    		forceFullWidth:"on"
    	});

    	$('#rsDemoWrapper').css('visibility', 'visible');
    }

    
    
    /*
    |--------------------------------------------------------------------------
    | CAMERA SLIDER
    |--------------------------------------------------------------------------
    */ 
    if($('.camera_wrap').length){

    	jQuery('.camera_wrap').camera({
    		thumbnails: true,
    		pagination: true,
    		playPause: false,
    		height:'50%',
    		fx:'simpleFade'
    	});

    }

    if($('.camera_wrap_nonav').length){

    	jQuery('.camera_wrap_nonav').camera({
    		pagination: false,
    		thumbnails: true,
    		height:'70%'
    	});

    }  
    if($('.camera_wrap_nothumb').length){

    	jQuery('.camera_wrap_nothumb').camera({
    		pagination: true,
    		thumbnails: false,
    		height:'40%'
    	});

    }  

    /*
    |--------------------------------------------------------------------------
    | ROLLOVER BTN
    |--------------------------------------------------------------------------
    */ 

    if($('.imgHover').length){

    	if(!Modernizr.csstransitions && !Modernizr.touch){

    		$('.imgHover figure').addClass('noCss3');
    		$('.imgHover figure').hover(
    			function() {

    				if( $(this).parents('.imgHover').hasClass('neko-hover-1') ){
    					$('img', this).stop(true, false).animate({
    						bottom: '+=40px'
    					}, 400, 'easeOutQuad',function() {}).end();


    					var captionHeight = $('figcaption', this).outerHeight(true);

    					$('figcaption', this).stop(true, false).animate({

    						bottom: captionHeight

    					}, 400, 'easeOutQuad',function() {}).end();	
    				}else if( $(this).parents('.imgHover').hasClass('neko-hover-2') ){

    					$('.pinInfo h1, .pinInfo h2, .pinInfo h3, .pinInfo h4, .pinInfo h5, .pinInfo h6', this).css({ 
    						"-ms-transform":"translateY(0)",
    						"opacity": 1
    					});

    					$('.pinInfo p', this).css({ 
    						"opacity": 1
    					});

    					$('.iconLinks', this).css({ "-ms-transform":"translate(-50%,0)"});
    					$('.projectLink', this).css({ "-ms-transform":"translate(-50%,0)"});

    					$('figcaption', this).animate({
    						opacity: 1},
    						300, function() {

    						});
    				}




    			}, function() {

    				if( $(this).parents('.imgHover').hasClass('neko-hover-1')  ){
    					$('img', this).stop(true, false).animate({
    						bottom: '0'
    					}, 400, 'easeOutQuad',function() {}).end();

    					$('figcaption', this).stop(true, false).animate({
    						bottom: 0
    					}, 400, 'easeOutQuad',function() {}).end();				
    				}else if( $(this).parents('.imgHover').hasClass('neko-hover-2')  ){

    					$('figcaption', this).animate({
    						opacity: 0},
    						300, function() {

    						});
    				}

    			});
}
}



    /*
    |--------------------------------------------------------------------------
    | ROLLOVER BTN
    |--------------------------------------------------------------------------
    */ 

    $('.socialIcon').hover(
    	function () {
    		$(this).stop(true, true).addClass('socialHoverClass', 300);
    	},
    	function () {
    		$(this).removeClass('socialHoverClass', 300);
    	});





    $('.tabs li, .accordion h2').hover(
    	function () {
    		$(this).stop(true, true).addClass('speBtnHover', 300);
    	},
    	function () {
    		$(this).stop(true, true).removeClass('speBtnHover', 100);
    	});



    /*
    |--------------------------------------------------------------------------
    | ALERT
    |--------------------------------------------------------------------------
    */ 
    $('.alert').delegate('button', 'click', function() {
    	$(this).parent().fadeOut('fast');
    });
    
    
    /*
    |--------------------------------------------------------------------------
    | CLIENT
    |--------------------------------------------------------------------------
    */   
    
    if($('.colorHover').length){
    	var array =[];
    	$('.colorHover').hover(

    		function () {

    			array[0] = $(this).attr('src');
    			$(this).attr('src', $(this).attr('src').replace('-off', ''));

    		}, 

    		function () {

    			$(this).attr('src', array[0]);

    		});
    }



    /*
    |--------------------------------------------------------------------------
    | Rollover boxIcon
    |--------------------------------------------------------------------------
    */ 
    if($('.boxIcon').length){

    	$('.boxIcon').hover(function() {
    		var $this = $(this);

    		$this.css('opacity', '1');   
            //$this.find('.boxContent>p').stop(true, false).css('opacity', 0);
            $this.addClass('hover');
            $('.boxContent>p').css('bottom', '-50px');
            $this.find('.boxContent>p').stop(true, false).css('display', 'block');

            $this.find('.iconWrapper i').addClass('triggeredHover');    

            $this.find('.boxContent>p').stop(true, false).animate({
            	'margin-top': '0px'},
            	300, function() {
            // stuff to do after animation is complete
        });


        }, function() {
        	var $this = $(this);
        	$this.removeClass('hover');

        	$this.find('.boxContent>p').stop(true, false).css('display', 'none');
        	$this.find('.boxContent>p').css('margin-top', '250px');
        	$this.find('.iconWrapper i').removeClass('triggeredHover'); 


        });   
    }   



/*
|--------------------------------------------------------------------------
| ROLL OVER PreviewTrigger
|--------------------------------------------------------------------------
*/
if($('.previewTrigger').length){

	$('.mask').css('height', $('.previewTrigger').height());
	$('.mask').css('width', $('.previewTrigger').width());
    // $('.mask', this).css('top', $('.previewTrigger', this).width());
    // $('.mask', this).css('left', $('.previewTrigger', this).width());

    $('.previewTrigger').hover(function() {

    	var $this = $(this);

    	$this.children('.mask').fadeIn('fast');

    	if(Modernizr.csstransitions) {
    		$('.iconWrapper', $this).addClass('animated');
    		$('.iconWrapper', $this).css('display', 'block');
    		$('.iconWrapper', $this).removeClass('flipOutX'); 
    		$('.iconWrapper', $this).addClass('bounceInDown'); 
    	}else{
    		$('.iconWrapper', $this).stop(true, false).fadeIn('fast');
    	}

    }, function() {

    	var $this = $(this); 

    	$this.children('.mask').fadeOut('fast');

    	if(Modernizr.csstransitions) {
    		$('.iconWrapper', $this).removeClass('bounceInDown'); 
    		$('.iconWrapper', $this).addClass('flipOutX');
    		$('.iconWrapper', $this).css('display', 'none');
    		$('.iconWrapper', $this).removeClass('animated');
    	}else{
    		$('.iconWrapper', $this).stop(true, false).fadeOut('fast');
    	}

    });
}





/*
|--------------------------------------------------------------------------
| PORTFOLIO SHEET SYSTEM
|--------------------------------------------------------------------------
*/
// PAGE SLIDE
/*$(".portfolioSheet").pageslide({
    direction: "left",
    modal: true,
    iframe: false,
    speed: "250"
});*/



/*
|--------------------------------------------------------------------------
| BACKGROUND VIDEO
|--------------------------------------------------------------------------
*/
if($('#videoBg').length){
	$('#videoBg').mb_YTPlayer();
}

/*
|--------------------------------------------------------------------------
| HTML5 video
|--------------------------------------------------------------------------
*/


if ($('#html5Video').length){

	var videobackground = new $.backgroundVideo($('#html5Video'), {
		"align": "centerXY",
		"width": 1280,
		"height": 780,
		"path": "video/",
		"filename": "explore",
		"types": ["mp4","ogg","webm"],
		"poster": "poster.jpg",
		"responsiveWidth": 1024
	});

}

/*
|--------------------------------------------------------------------------
| APPEAR
|--------------------------------------------------------------------------
*/
if($('.activateAppearAnimation').length){

	nekoAnimAppear();
	$('.reloadAnim').click(function (e) {
		$(this).parent().parent().find('img[data-nekoanim]').attr('class', '').addClass('img-responsive');
		nekoAnimAppear();
		e.preventDefault();
	});
}



//END DOCUMENT READY   
});


/*
|--------------------------------------------------------------------------
| EVENTS TRIGGER AFTER ALL IMAGES ARE LOADED
|--------------------------------------------------------------------------
*/
$(window).load(function() {

	"use strict";

    /*
    |--------------------------------------------------------------------------
    | PRELOADER
    |--------------------------------------------------------------------------
    */
    if($('#nekoStatus').length){

    	$('#nekoStatus').fadeOut('slow', function() {
    		  $('#preloader').fadeOut('slow');
    	});
 
    }


    /*
    |--------------------------------------------------------------------------
    | ISOTOPE USAGE FILTERING
    |--------------------------------------------------------------------------
    */ 
    if($('.isotopeWrapper').length){

    	var $container = $('.isotopeWrapper');
    	var $resize = $('.isotopeWrapper').attr('id');
        // initialize isotope
        
        $container.isotope({
        	layoutMode: 'sloppyMasonry',
        	itemSelector: '.isotopeItem',
        	resizable: false,
        	masonry: {
        		columnWidth: $container.width() / $resize
        	}

        });


        //var rightHeight = $('#works').height();
        $('#filter a').click(function(e){


        	//$('#works').height(rightHeight);
        	$('#filter a').removeClass('current');


        	$(this).addClass('current');
        	var selector = $(this).attr('data-filter');
        	
        	$container.isotope({
        		filter: selector,
        		animationOptions: {
        			duration: 300,
        			easing: 'easeOutQuart'
        		}
        	});

        	if (isDesktop === true && $('section[id^="paralaxSlice"]').length){
        		setTimeout(function(){
        			$.stellar('refresh');
        		}, 1000);
        	}

        	e.preventDefault();
        	return false;
        });
        
        
        $(window).smartresize(function(){
        	$container.isotope({
                // update columnWidth to a percentage of container width
                masonry: {
                	columnWidth: $container.width() / $resize
                }
            });
        });
        

    }  









    /**PROCESS ICONS**/
    $('.iconBoxV3 a').hover(function() {

    	if(Modernizr.csstransitions) {

    		$(this).stop(false, true).toggleClass( 'hover', 150);
    		$('i', this).css('-webkit-transform', 'rotateZ(360deg)');
    		$('i', this).css('-moz-transform', 'rotateZ(360deg)');
    		$('i', this).css('-o-transform', 'rotateZ(360deg)');
    		$('i', this).css('transform', 'rotateZ(360deg)'); 

    	}else{

    		$(this).stop(false, true).toggleClass( 'hover', 150);

    	}  

    }, function() {

    	if(Modernizr.csstransitions) {
    		$(this).stop(false, true).toggleClass( 'hover', 150);
    		$('i', this).css('-webkit-transform', 'rotateZ(0deg)');
    		$('i', this).css('-moz-transform', 'rotateZ(0deg)');
    		$('i', this).css('-o-transform', 'rotateZ(0deg)');
    		$('i', this).css('transform', 'rotateZ(0deg)'); 

    	}else{

    		$(this).stop(false, true).toggleClass( 'hover', 150);
    	}  

    });




    if (isDesktop === true && $('section[id^="paralaxSlice"]').length )
    {

    	$(window).stellar({
    		horizontalScrolling: false,
    		responsive:true
    	});
    }





//END WINDOW LOAD
});

/*
|--------------------------------------------------------------------------
| FUNCTIONS
|--------------------------------------------------------------------------
*/

function fullscreenImage(){
	$('#homeFullScreen').css({height:$(window).height()});
	$('#homeFullScreen').css({width:$(window).width()});
}



/* Appear function */
function nekoAnimAppear(){
	$("[data-nekoanim]").each(function() {

		var $this = $(this);

		$this.addClass("nekoAnim-invisible");
		
		if($(window).width() > 767) {
			
			$this.appear(function() {

				var delay = ($this.data("nekodelay") ? $this.data("nekodelay") : 1);
				if(delay > 1) $this.css("animation-delay", delay + "ms");

				$this.addClass("nekoAnim-animated");
				$this.addClass('nekoAnim-'+$this.data("nekoanim"));

				setTimeout(function() {
					$this.addClass("nekoAnim-visible");
				}, delay);

			}, {accX: 0, accY: -150});

		} else {
			$this.animate({ opacity: 1 }, 300, 'easeInOutQuad',function() { });
		}
	});
}


/* Append more link on mosaic profolio */

function moreLinkMosaicPorfolio(linkTxt, linlUrl, icon){


	if(!$('.generatedMoreLink').length){
		$('.mosaicMoreLink').append('<article class="generatedMoreLink valign"><a href="'+linlUrl+'" class="valigned">'+linkTxt+'<i class="icon-'+icon+'"></i></a></article>');

		$('.generatedMoreLink').css('cursor', 'pointer');

		$('.generatedMoreLink').click(function (e) {
			document.location.href=linlUrl;
			e.preventDefault();
		});
	}
	
	$('.generatedMoreLink').css('display', 'none');
	var boxHeight;
	var linkPaddingTop;

	$(window).on("load resize",function(e){
		boxHeight = $('.mosaicMoreLink article:first-child')[0].getBoundingClientRect().height;
		$('.generatedMoreLink').height( boxHeight );
		$('.generatedMoreLink').slideDown('slow');

	});

}





/* CONTACT FROM */
$(function() {
	"use strict";
	if( $("#contactfrm").length ){

		$.validator.setDefaults({
			highlight: function(element) {
				$(element).closest('.form-group').addClass('has-error has-feedback');
				if(!$(element).closest('.form-group').find('.form-control-feedback').length){
					$(element).closest('.form-group').append('<span class="icon-cancel form-control-feedback"></span>');
				}
				
			},
			unhighlight: function(element) {
				$(element).closest('.form-group').removeClass('has-error has-feedback');
				$(element).closest('.form-group').find('.form-control-feedback').remove();
			},
			errorElement: 'span',
			errorClass: 'help-block',
			errorPlacement: function(error, element) {
				if(element.parent('.input-group').length) {
					error.insertAfter(element.parent());
				} else {
					error.insertAfter(element);
				}
			}
		});


		$("#contactfrm").validate({
			/* debug: true, */
			submitHandler: function(form) {

				$(form).ajaxSubmit({
					target: ".result",
					success: function(){
						if($('.result .alert-success').length){
							$("#contactfrm").trigger('reset');

						}
					}
				});
			},
			onkeyup: false,
			onclick: false,
			rules: {
				name: {
					required: true,
					minlength: 3
				},
				email: {
					required: true,
					email: true
				},
				phone: {
					required: true,
					minlength: 10,
					digits:true
				},
				comment: {
					required: true,
					minlength: 10,
					maxlength: 350
				}
			}
		});
	}

});

/* CONTACT FROM */ 

/* FLEXSLIDER INNER INFO CUSTOM ANIMATION */
function animateTxt(curSlide, action){
	"use strict";
	if(action === 'in'){
		var i = 0;
		var animaDelay = 0;

		$('.slideN'+curSlide+':not([class*=clone])>.caption').css('display', 'block');

		$('.slideN'+curSlide+':not([class*=clone])>.caption>div').each(function( ) {
			if(Modernizr.csstransitions) { 

				$(this).css('-webkit-animation-delay', animaDelay+'s');
				$(this).css('-moz-animation-delay', animaDelay+'s');
				$(this).css('-0-animation-delay', animaDelay+'s');
				$(this).css('-ms-animation-delay', animaDelay+'s');
				$(this).css('animation-delay-delay', animaDelay+'s');

				$(this).show().addClass('animated').addClass($(this).attr('data-animation'));


                // $(this).show('slow', function() {
                //     $(this).addClass('animated').addClass($(this).attr('data-animation'));
                // });


	}else{
		var timing;
		$('.slideN'+curSlide+':not([class*=clone])>.caption>div').hide();
		if (i === 0){timing = 0;}else if(i === 1){timing = 300;} else{ timing = 300 * i;}
		$(this).delay(timing).fadeIn('fast');
	}
	i++;
	animaDelay = animaDelay+0.2;


});

	}else{
		var j = 0;
		$('.slideN'+curSlide+':not([class*=clone])>.caption').css('display', 'none');

		$('.slideN'+curSlide+':not([class*=clone])>.caption>div').each(function( ) {
			if(Modernizr.csstransitions) { 

				$(this).removeClass($(this).attr('data-animation')).removeClass('animated').hide();

			}else{
				$(this).hide();
			}
			j++;
		});
	}

}



/* MAIN MENU (submenu slide and setting up of a select box on small screen)*/
function initializeMainMenu() {

	"use strict";
	var $mainMenu = $('#mainMenu').children('ul');


	/* Mobile */
	if(Modernizr.mq('only all and (max-width: 1024px)') ) {


			// Responsive Menu Events
			var addActiveClass = false;

			$("a.hasSubMenu").unbind('click');
			$('li',$mainMenu).unbind('mouseenter mouseleave');

			$("a.hasSubMenu").on("click", function(e) {
				
				var $this = $(this);
				e.preventDefault();


				addActiveClass = $this.parent("li").hasClass("Nactive");


				$this.parent("li").removeClass("Nactive");
				$this.next('.subMenu').slideUp('fast');

				

				if(!addActiveClass) {
					$this.parents("li").addClass("Nactive");
					$this.next('.subMenu').slideDown('fast');
				}else{
					$this.parent().parent('li').addClass("Nactive");
				}

				return;
				
			});

			/* Tablet */	
		}else{


			$("li", $mainMenu).removeClass("Nactive");
			$('a', $mainMenu).unbind('click');


			$('li',$mainMenu).hover(

				function() {

					var $this = $(this),
					$subMenu = $this.children('.subMenu');


					if( $subMenu.length ){
						$this.addClass('hover').stop();
					}else {

						if($this.parent().is($(':gt(1)', $mainMenu))){

							$this.stop(false, true).fadeIn('slow');

						}
					}


					if($this.parent().is($(':gt(1)', $mainMenu))){

						$subMenu.stop(true, true).fadeIn(200,'easeInOutQuad'); 
						$subMenu.css('left', $subMenu.parent().outerWidth(true));


					}else{

						$subMenu.stop(true, true).delay( 200 ).fadeIn(200,'easeInOutQuad'); 

					}

				}, function() {

					var $this = $(this),
					$subMenu = $this.children('ul, div');

					if($this.parent().is($(':gt(1)', $mainMenu))){


						$this.children('ul').hide();
						$this.children('ul').css('left', 0);


					}else{

						$this.removeClass('hover');
						$subMenu.stop(true, true).delay( 200 ).fadeOut();
					}

					if( $subMenu.length ){$this.removeClass('hover');}

				});

		}
	}






/*
|--------------------------------------------------------------------------
| GOOGLE MAP
|--------------------------------------------------------------------------
*/

function appendBootstrap() {
	"use strict";
	if($('#mapWrapper').length){
		var script = document.createElement("script");
		script.type = "text/javascript";
		script.src = "https://maps.googleapis.com/maps/api/js?callback=initialize";
		document.body.appendChild(script);
	}

	if($('#mapWrapperSatellite').length){
		var script = document.createElement("script");
		script.type = "text/javascript";
		script.src = "https://maps.googleapis.com/maps/api/js?callback=initializeSatellite";
		document.body.appendChild(script);
	}
}     




function initialize(id) {
	"use strict";
	var image = 'images/icon-map.png';

	var overlayTitle = 'Agencies';

	var locations = [
        //point number 1
        ['Madison Square Garden', '4 Pennsylvania Plaza, New York, NY'],

        //point number 2
        ['Best town ever', 'Santa Cruz', 36.986021, -122.02216399999998],

        //point number 3 
        ['Located in the Midwestern United States', 'Kansas'],

        //point number 4
        ['I\'ll definitly be there one day', 'Chicago', 41.8781136, -87.62979819999998] 
        ];

        /*** DON'T CHANGE ANYTHING PASSED THIS LINE ***/
        id = (id === undefined) ? 'mapWrapper' : id;

        var map = new google.maps.Map(document.getElementById(id), {
        	mapTypeId: google.maps.MapTypeId.ROADMAP,
        	scrollwheel: false,
        	zoomControl: true,
        	zoomControlOptions: {
        		style: google.maps.ZoomControlStyle.LARGE,
        		position: google.maps.ControlPosition.LEFT_CENTER
        	},
        	streetViewControl:true,
        	scaleControl:false,
        	zoom: 14,
        	styles:[
        	{
        		"featureType": "water",
        		"stylers": [
        		{
        			"color": "#f7f7f7"
        		},
        		]
        	},
        	{
        		"featureType": "road",
        		"elementType": "geometry.fill",
        		"stylers": [
        		{
        			"color": "#FCFFF5"
        		},
        		]
        	},
        	{
        		"featureType": "road",
        		"elementType": "geometry.stroke",
        		"stylers": [
        		{
        			"color": "#808080"
        		},
        		{
        			"lightness": 54
        		}
        		]
        	},
        	{
        		"featureType": "landscape.man_made",
        		"elementType": "geometry.fill",
        		"stylers": [
        		{
        			"color": "#dde1d4"
        		}
        		]
        	},
        	{
        		"featureType": "poi.park",
        		"elementType": "geometry.fill",
        		"stylers": [
        		{
        			"color": "#73AB7D"
        		}
        		]
        	},
        	{
        		"featureType": "road",
        		"elementType": "labels.text.fill",
        		"stylers": [
        		{
        			"color": "#767676"
        		}
        		]
        	},
        	{
        		"featureType": "road",
        		"elementType": "labels.text.stroke",
        		"stylers": [
        		{
        			"color": "#ffffff"
        		}
        		]
        	},
        	{
        		"featureType": "road.highway",
        		"elementType": "geometry.fill",
        		"stylers": [
        		{
        			"color": "#7e7341"
        		}
        		]
        	},

        	{
        		"featureType": "landscape.natural",
        		"elementType": "geometry.fill",
        		"stylers": [
        		{
        			"visibility": "on"
        		},
        		{
        			"color": "#dee6e6"
        		}
        		]
        	},
        	{
        		"featureType": "poi.park",
        		"stylers": [
        		{
        			"visibility": "on"
        		}
        		]
        	},
        	{
        		"featureType": "poi.sports_complex",
        		"stylers": [
        		{
        			"visibility": "on"
        		}
        		]
        	},
        	{
        		"featureType": "poi.medical",
        		"stylers": [
        		{
        			"visibility": "on"
        		}
        		]
        	},
        	{
        		"featureType": "poi.business",
        		"stylers": [
        		{
        			"visibility": "simplified"
        		}
        		]
        	}
        	]

        });

var myLatlng;
var marker, i;
var bounds = new google.maps.LatLngBounds();
var infowindow = new google.maps.InfoWindow({ content: "loading..." });

for (i = 0; i < locations.length; i++) { 


	if(locations[i][2] !== undefined && locations[i][3] !== undefined){
		var content = '<div class="infoWindow">'+locations[i][0]+'<br>'+locations[i][1]+'</div>';
		(function(content) {
			myLatlng = new google.maps.LatLng(locations[i][2], locations[i][3]);

			marker = new google.maps.Marker({
				position: myLatlng,
				icon:image,  
				title: overlayTitle,
				map: map
			});

			google.maps.event.addListener(marker, 'click', (function() {
				return function() {
					infowindow.setContent(content);
					infowindow.open(map, this);
				};

			})(this, i));

			if(locations.length > 1){
				bounds.extend(myLatlng);
				map.fitBounds(bounds);
			}else{
				map.setCenter(myLatlng);
			}

		})(content);
	}else{

		var geocoder   = new google.maps.Geocoder();
		var info   = locations[i][0];
		var addr   = locations[i][1];
		var latLng = locations[i][1];

		(function(info, addr) {

			geocoder.geocode( {

				'address': latLng

			}, function(results) {

				myLatlng = results[0].geometry.location;

				marker = new google.maps.Marker({
					position: myLatlng,
					icon:image,  
					title: overlayTitle,
					map: map
				});
				var $content = '<div class="infoWindow">'+info+'<br>'+addr+'</div>';
				google.maps.event.addListener(marker, 'click', (function() {
					return function() {
						infowindow.setContent($content);
						infowindow.open(map, this);
					};
				})(this, i));

				if(locations.length > 1){
					bounds.extend(myLatlng);
					map.fitBounds(bounds);
				}else{
					map.setCenter(myLatlng);
				}
			});
		})(info, addr);

	}
}
}


function initializeSatellite(id) {
	"use strict";
	var image = 'images/icon-map.png';

	var overlayTitle = 'Agencies';

	var locations = [
        //point number 1
        ['Best town ever', 'Santa Cruz', 36.986021, -122.02216399999998]
        ];

        /*** DON'T CHANGE ANYTHING PASSED THIS LINE ***/
        id = (id === undefined) ? 'mapWrapperSatellite' : id;

        var map = new google.maps.Map(document.getElementById(id), {
        	mapTypeId: 'satellite',
        	scrollwheel: false,
        	zoomControl: true,
        	zoomControlOptions: {
        		style: google.maps.ZoomControlStyle.LARGE,
        		position: google.maps.ControlPosition.LEFT_CENTER
        	},
        	streetViewControl:true,
        	scaleControl:false,
        	zoom:18
        });
        map.setTilt(45);
        var myLatlng;
        var marker, i;
        var bounds = new google.maps.LatLngBounds();
        var infowindow = new google.maps.InfoWindow({ content: "loading..." });

        for (i = 0; i < locations.length; i++) { 


        	if(locations[i][2] !== undefined && locations[i][3] !== undefined){
        		var content = '<div class="infoWindow">'+locations[i][0]+'<br>'+locations[i][1]+'</div>';
        		(function(content) {
        			myLatlng = new google.maps.LatLng(locations[i][2], locations[i][3]);

        			marker = new google.maps.Marker({
        				position: myLatlng,
        				icon:image,  
        				title: overlayTitle,
        				map: map
        			});

        			google.maps.event.addListener(marker, 'click', (function() {
        				return function() {
        					infowindow.setContent(content);
        					infowindow.open(map, this);
        				};

        			})(this, i));

        			if(locations.length > 1){
        				bounds.extend(myLatlng);
        				map.fitBounds(bounds);
        			}else{
        				map.setCenter(myLatlng);
        			}

        		})(content);
        	}else{

        		var geocoder   = new google.maps.Geocoder();
        		var info   = locations[i][0];
        		var addr   = locations[i][1];
        		var latLng = locations[i][1];

        		(function(info, addr) {

        			geocoder.geocode( {

        				'address': latLng

        			}, function(results) {

        				myLatlng = results[0].geometry.location;

        				marker = new google.maps.Marker({
        					position: myLatlng,
        					icon:image,  
        					title: overlayTitle,
        					map: map
        				});
        				var $content = '<div class="infoWindow">'+info+'<br>'+addr+'</div>';
        				google.maps.event.addListener(marker, 'click', (function() {
        					return function() {
        						infowindow.setContent($content);
        						infowindow.open(map, this);
        					};
        				})(this, i));

        				if(locations.length > 1){
        					bounds.extend(myLatlng);
        					map.fitBounds(bounds);
        				}else{
        					map.setCenter(myLatlng);
        				}
        			});
        		})(info, addr);

        	}
        }
    }







    /* ANALYTICS */
    function gaSSDSLoad (acct) {
    	"use strict";  
    	var gaJsHost = (("https:" === document.location.protocol) ? "https://ssl." : "http://www."),
    	pageTracker,
    	s;
    	s = document.createElement('script');
    	s.src = gaJsHost + 'google-analytics.com/ga.js';
    	s.type = 'text/javascript';
    	s.onloadDone = false;
    	function init () {
    		pageTracker = _gat._getTracker(acct);
    		pageTracker._trackPageview();
    	}
    	s.onload = function () {
    		s.onloadDone = true;
    		init();
    	};
    	s.onreadystatechange = function() {
    		if (('loaded' === s.readyState || 'complete' === s.readyState) && !s.onloadDone) {
    			s.onloadDone = true;
    			init();
    		}
    	};
    	document.getElementsByTagName('head')[0].appendChild(s);
    }




    jQuery(function($){
    	"use strict";
    	if($('#superSizedSlider').length){

    		$('#superSizedSlider').height($(window).height());

    		$.supersized({

                    // Functionality
                    slideshow               :   1,          // Slideshow on/off
                    autoplay                :   1,          // Slideshow starts playing automatically
                    start_slide             :   1,          // Start slide (0 is random)
                    stop_loop               :   0,          // Pauses slideshow on last slide
                    random                  :   0,          // Randomize slide order (Ignores start slide)
                    slide_interval          :   12000,      // Length between transitions
                    transition              :   1,          // 0-None, 1-Fade, 2-Slide Top, 3-Slide Right, 4-Slide Bottom, 5-Slide Left, 6-Carousel Right, 7-Carousel Left
                    transition_speed        :   1000,       // Speed of transition
                    new_window              :   1,          // Image links open in new window/tab
                    pause_hover             :   0,          // Pause slideshow on hover
                    keyboard_nav            :   1,          // Keyboard navigation on/off
                    performance             :   1,          // 0-Normal, 1-Hybrid speed/quality, 2-Optimizes image quality, 3-Optimizes transition speed // (Only works for Firefox/IE, not Webkit)
                    image_protect           :   1,          // Disables image dragging and right click with Javascript

                    // Size & Position                         
                    min_width               :   0,          // Min width allowed (in pixels)
                    min_height              :   0,          // Min height allowed (in pixels)
                    vertical_center         :   1,          // Vertically center background
                    horizontal_center       :   1,          // Horizontally center background
                    fit_always              :   0,          // Image will never exceed browser width or height (Ignores min. dimensions)
                    fit_portrait            :   1,          // Portrait images will not exceed browser height
                    fit_landscape           :   0,          // Landscape images will not exceed browser width

                    // Components                           
                    slide_links             :   'blank',    // Individual links for each slide (Options: false, 'num', 'name', 'blank')
                    thumb_links             :   0,          // Individual thumb links for each slide
                    thumbnail_navigation    :   0,          // Thumbnail navigation
                    slides                  :   [           // Slideshow Images
                    {image : './images/slider/super/supersized-1.jpg', title : '<h1>We are a creative agency</h1><a href="#team" class="btn btn-primary">learn more</a>', thumb : '', url : ''},

                    {image : './images/slider/super/supersized-2.jpg', title : '<h1>We build beautiful websites</h1><a href="#works" class="btn btn-primary">check our portfolio</a>', thumb : '', url : ''},

                    {image : './images/slider/super/supersized-3.jpg', title : '<h1>That\'s how we like it</h1><a href="#contact" class="btn btn-primary">call us</a>', thumb : '', url : ''}
                    ],

                    // Theme Options               
                    progress_bar            :   0,          // Timer for each slide                         
                    mouse_scrub             :   0
                    
                });
}
});

/* TO TOP BUTTON */

function toTop(mobile){

	if(mobile == false){

		if(!$('#nekoToTop').length)
			$('body').append('<a href="#" id="nekoToTop"><i class="icon-up-open"></i></a>');

		$(window).scroll(function () {
			if ($(this).scrollTop() > 100) {
				$('#nekoToTop').slideDown('fast');
			} else {
				$('#nekoToTop').slideUp('fast');
			}
		});

		$('#nekoToTop').click(function (e) {
			e.preventDefault();
			$("html, body").animate({
				scrollTop: 0
			}, 800, 'easeInOutCirc');

		});
	}else{

		if($('#nekoToTop').length)
			$('#nekoToTop').remove();

	}

}

//---------------------------------Andrews Custom JS CODES------------------------//



//---------------------------------FIXES THAT MODEL SHIT----------------------//
//https://github.com/twbs/bootstrap/issues/9855//

$(document).ready(function () {
    $('.modal').on('show.bs.modal', function () {
        if ($(document).height() > $(window).height()) {
            // no-scroll
            $('body').addClass("modal-open-noscroll");
        }
        else {
            $('body').removeClass("modal-open-noscroll");
        }
    })
    $('.modal').on('hide.bs.modal', function () {
        $('body').removeClass("modal-open-noscroll");
    })
})


//Reveal Toggle

$(function () {

    $('.toggle').click(function (event) {
        event.preventDefault();
        var target = $(this).attr('href');
        $(target).toggleClass('hidden show');
    });

});





//SDIV SWAPPER


function SwapDivsWithClick(div1,div2)
{
    d1 = document.getElementById(div1);
    d2 = document.getElementById(div2);
    if( d2.style.display == "none" )
    {
        d1.style.display = "none";
        d2.style.display = "block";
    }
    else
    {
        d1.style.display = "block";
        d2.style.display = "none";
    }
}



function homepageVideo() {
    var x = document.getElementById('myVideo');
    if (x.style.display === 'none') {
        x.style.display = 'block';
    } else {
        x.style.display = 'none';
    }
}



//Sidebar Java Script
//$(window).bind('resize load', function () {
//    if ($(this).width() < 767) {
//        $('#accordion').removeClass('in');
//        $('#accordion').addClass('out');
//    } else {
//        $('#accordion').removeClass('out');
//        $('#accordion').addClass('in');
//    }
//});
//---------------------------------FIXES THAT FUCKING MODEL SHIT----------------------//