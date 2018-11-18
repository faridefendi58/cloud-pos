(function() {
  'use strict';
  
  $('.mdl-navigation__toggle .mdl-navigation__link').on('click',function(){
    if($(this).parent().attr('class') !== undefined ){
      $(this).toggleClass('is_slide');
      $(this).parent().children('.mdl-navigation__dropdown').slideToggle();
    }
  });

   $('.mdl-close').on('click',function(){
      $(this).parent().remove();
   });
   
   $("[data-toggle]").map(function(key) {
    var toggle = $(this).attr('data-toggle');
      $(toggle).toggleClass('hide_sb_right');
      $('[data-toggle="'+toggle+'"]').click(function(){
        $(toggle).toggleClass("show_sb_right hide_sb_right");
      });
  }).get();

   var all = $("[data-target]").map(function(key) {
    var target = $(this).attr('data-target');
      $('[data-target="'+target+'"]').click(function(){
        $(target).show();
        $('.dialog-close').click(function(){
          $(target).hide();
        });
      });
  }).get();

 }());
