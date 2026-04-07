( function( $ ) {
	class LoadMoreAuthorVids {
		constructor() {
		this.ajaxUrl = ajax_author_thumb?.ajaxUrl ?? '';
		this.ajaxNonce = ajax_author_thumb?.ajax_nonce ?? '';
		this.loadMoreAuthorVidsBtn = $( '#load-more-author-vids' );
		this.ajaxAuthorId = ajax_author_thumb?.author_id ?? '';

		this.options = {
			root: null,
			rootMargin: '50px',
			threshold: 1.0, // 1.0 means set isIntersecting to true when element comes in 100% view.
		};

		this.init();
  
	}
  
	init() {
  
		if ( ! this.loadMoreAuthorVidsBtn.length ) {
		 return;
		}
		this.totalPagesCount = $( '#post-pagination' ).data( 'max-pages' );
		/**
		 * Add the IntersectionObserver api, and listen to the load more intersection status.
		 * so that intersectionObserverCallback gets called if the element intersection status changes.
		 *
		 * @type {IntersectionObserver}
		 */
		let observer = new IntersectionObserver( ( entries ) => this.intersectionObserverCallback( entries ), this.options );
		observer.observe( this.loadMoreAuthorVidsBtn[0] );
	}
  
	/**
	 * Gets called on initial render with status 'isIntersecting' as false and then
	 * everytime element intersection status changes.
	 *
	 * @param {array} entries No of elements under observation.
	 *
	 * @return null
	 */
	intersectionObserverCallback( entries ) { // array of observing elements
  
		// The logic is apply for each entry ( in this case it's just one loadmore button )
		entries.forEach( entry => {
		  // If load more button in view.
		  if ( entry?.isIntersecting ) {
			this.handleLoadMoreAuthorVidsPosts();
		  }
		} );
	}
  
	/**
	 * Load more posts.
	 *
	 * 1.Make an ajax request, by incrementing the page no. by one on each request.
	 * 2.Append new/more posts to the existing content.
	 * 3.If the response is 0 ( which means no more posts available ), remove the load-more button from DOM.
	 * Once the load-more button gets removed, the IntersectionObserverAPI callback will not be triggered, which means
	 * there will be no further ajax request since there won't be any more posts available.
	 *
	 * @return null
	 */
	handleLoadMoreAuthorVidsPosts() {
  
		// Get page no from data attribute of load-more button.
		const page = this.loadMoreAuthorVidsBtn.data( 'page' );
		if ( !page ) {
		 	return null;
		}
  
		const nextPage = parseInt(page) + 1; // Increment page count by one.
  
		$.ajax( {
		  	url: this.ajaxUrl,
		  	type: 'post',
		  	data: {
				page: page,
				author_id: this.ajaxAuthorId,
				action: 'load_more_author_vids',
				ajax_nonce: this.ajaxNonce
		  	},
		  	success: ( response ) => {
				if( response != 0 ) {
					this.loadMoreAuthorVidsBtn.data( 'page', nextPage );
					$( '#load-more-author-vids-content' ).append( response );
					this.removeLoadMoreAuthorVidsIfOnLastPage(nextPage);
				}else{
					this.loadMoreAuthorVidsBtn.remove();
				}    
		  	},
			// success: ( response ) => {
			// 	this.loadMoreAuthorVidsBtn.data( 'page', nextPage );
			// 	$( '#load-more-author-vids-content' ).append( response );
			// 	this.removeLoadMoreIfOnLastPage(nextPage)         
			// },
		  	error: ( response ) => {
				console.log( response );
		  	},
		} );
	}
	/**
	 * Remove Load more Button If on last page.
	 *
	 * @param {int} nextPage New Page.
	 */
	removeLoadMoreAuthorVidsIfOnLastPage = ( nextPage ) => {
		if ( nextPage + 1 > this.totalPagesCount ) {
		this.loadMoreAuthorVidsBtn.remove();
		}
	}
}
  
new LoadMoreAuthorVids();
  
} )( jQuery );