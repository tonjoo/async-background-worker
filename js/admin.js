jQuery(
	function($){
		$( document ).ready(
			function(){
				$( 'ul.tabs li' ).click(
					function(){
						var tab_id = $( this ).attr( 'data-tab' );

						$( 'ul.tabs li' ).removeClass( 'current' );
						$( '.tab-content' ).removeClass( 'current' );

						$( this ).addClass( 'current' );
						$( "#" + tab_id ).addClass( 'current' );
					}
				);
			}
		);

		$( '.btn-bw-retry-job' ).click(
			function(e){
				e.preventDefault();

				var el = $( this ),
				td = el.closest( 'td' );

				var dataForm = {
					id: $( this ).attr( 'data-job-ID' )
				};

				var dataPost = {
					'action': 'retry_background_worker_job',
					'dataForm': dataForm
				};

				el.addClass( 'disabled' );
				td.find( '.spinner' ).removeClass( 'hide' ).addClass( 'show' );

				$.ajax(
					{ // ajax form submit
						url : ajaxurl,
						type: 'POST',
						data : dataPost,
						dataType : "json",
						success: function(response){
							// console.log(response);
						},
						complete: function(jqXHR, status){
							// console.log(jqXHR);
							// console.log(status);
							var json_res = jqXHR.responseJSON;

							// console.log(json_res);
							td.html( json_res.message );

							td.find( '.spinner' ).removeClass( 'show' ).addClass( 'hide' );
							el.removeClass( 'disabled' );
						}
					}
				);
			}
		);
	}
);
