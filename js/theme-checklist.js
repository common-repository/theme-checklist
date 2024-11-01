jQuery(function($){

    $('.theme-checklist-list li .result > a').click( function(e){
        e.preventDefault();
        var $$ = $(this);
        var $li = $$.closest('li');

        if( $$.hasClass('result-active') ) {
            // Reset
            $li.find('.result a').removeClass('result-active');
            $.get( $li.find('.result').data('reset-url') );
        }
        else {
            $li.find('.result a').removeClass('result-active');
            $$.addClass('result-active');
            $.get( $$.attr('href') );
        }

        if ( $li.find('.result a.result-active').hasClass('item-fail') ) {
            $li.find('.notes').slideDown();
        }
        else {
            $li.find('.notes').slideUp();
        }
    } );

    $('#theme-checklist-reset').click(function(e){
        e.preventDefault();
        
        if(confirm($(this).data('confirm'))) {
            $.get( $('.theme-checklist-list').data('reset-url') );
            $('.theme-checklist-list .result a').removeClass('result-active');
            $('.theme-checklist-list .notes').slideUp().find('textarea').val('').trigger('autosize.resize');
        }
    });

    // Save the notes

    $('.theme-checklist-list a.save-notes').click(function(){
        var $li = $(this).closest('li');
        var $$ = $(this);

        $$.html($$.data('saving-text'));
        $.post( $li.data('save-notes-url'), { 'notes' : $li.find('.notes textarea').val() }, function(){
            $$.html($$.data('save-text'));
            $$.removeClass('unsaved-changes');
        } );
    });

    $('.theme-checklist-list li .notes textarea').each(function(){
        var $$ = $(this);
        var timer;

        $$.keyup(function(){
            $$.closest('.notes').find('a.save-notes').addClass('unsaved-changes');

            clearTimeout(timer);
            timer = setTimeout(function(){
                $$.closest('.notes').find('a.save-notes').click();
            }, 2500);
        })
    });

    // Save everything to the server
    var serverSync = function(){
        var data = {
            'checked' : {},
            'notes' : {}
        };

        $( '.theme-checklist-list li .result > a.result-active' ).each( function(){
            var $li = $(this).closest('li');
            data.checked[$li.data('id')] = $(this).hasClass('item-pass') ? 'pass' : 'fail';
        } );

        $( '.theme-checklist-list li .notes textarea' ).each( function(){
            if( $(this).val() != '' ) {
                var $li = $(this).closest('li');
                data.notes[$li.data('id')] = $(this).val();
            }
        });

        // Save everything to the server...
        //$.post( $('#theme-checklist-admin').data('sync-url'), {'data': JSON.stringify(data)}, function(){
        $.post( $('#theme-checklist-admin').data('sync-url'), data, function(){
        } );
    }

    // Handle the import window

    $('#theme-checklist-admin .import-export .import').click(function(e){
        e.preventDefault();
        $('#checklist-import-modal textarea').val('').trigger('autosize.resize');
        $('#checklist-import-modal').show();
        $('#checklist-import-modal-overlay').show();
    });

    $('#checklist-import-modal .button-cancel').click(function(e){
        e.preventDefault();
        $('#checklist-import-modal').hide();
        $('#checklist-import-modal-overlay').hide();
    });

    $('#checklist-import-modal .button-import').click(function(e){
        e.preventDefault();

        try {
            var data = JSON.parse( $('#checklist-import-modal textarea').val() );
        }
        catch(err) {
            alert( 'Problem with json' );
            return;
        }

        // Lets start by resetting everything
        $('.theme-checklist-list .result a').removeClass('result-active');
        $('.theme-checklist-list .notes').hide().find('textarea').val('').trigger('autosize.resize');

        if(typeof data.checked != 'undefined') {
            for (check_id in data.checked) {
                $('#checklist-check-' + check_id + ' .result a.item-' + data.checked[check_id]).addClass('result-active');
                if(data.checked[check_id] == 'fail') {
                    $('#checklist-check-' + check_id + ' .notes').show();
                }
            }
        }

        if(typeof data.notes != 'undefined') {
            for (check_id in data.notes) {
                $('#checklist-check-' + check_id + ' .notes textarea').val(data.notes[check_id]).trigger('autosize.resize');
            }
        }

        // Now lets save everything to the server
        serverSync();

        $('#checklist-import-modal').hide();
        $('#checklist-import-modal-overlay').hide();
    });

    // Make the notes textareas auto resize
    $('.theme-checklist-list .notes textarea').autosize({
        append : ''
    });
});