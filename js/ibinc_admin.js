jQuery(document).ready(function() {
    jQuery("span.more-help").live("click", function(e){
    	jQuery(this).parent().parent().children('td').children('.hints').toggle();
    });
});