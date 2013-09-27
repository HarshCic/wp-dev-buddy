<?php

/**
* A class for rendering the Twitter feed
*
* This is the class to call at the top when scripting
* the template tag. Be sure to offer a $feed_config
* array as the parameter to properly initialise the
* object instance.
*
* @version 1.0.2
*/
if ( ! class_exists( 'DB_Twitter_Feed' ) ) {

class DB_Twitter_Feed extends DB_Twitter_Feed_Base {

	/**
	* @var string Main Twitter URL
	* @since 1.0.0
	*/
	public $tw = 'https://twitter.com/';

	/**
	* @var string Twitter search URL
	* @since 1.0.0
	*/
	public $search = 'https://twitter.com/search?q=%23';

	/**
	* @var string Twitter intent URL
	* @since 1.0.0
	*/
	public $intent = 'https://twitter.com/intent/';

	/**
	* @var array If there are any errors after a check is made for such, they will be stored here
	* @since 1.0.2
	*/
	public $errors;


	/**
	* Configure data necessary for rendering the feed
	*
	* Get the feed configuration provided by the user
	* and use defaults for options not provided, check
	* for a cached version of the feed under the given
	* user, initialise a Twitter API object.
	*
	* @access public
	* @return void
	* @since 1.0.0
	*/
	public function __construct( $feed_config ) {

		$this->set_main_admin_vars();

	/*	Populate the $options property with the config options submitted
		by the user. Should any of the options not be set, fall back on
		stored values, then defaults */
		if ( ! is_array( $feed_config ) ) {
			$feed_config = array();
		}

		foreach ( $this->options as $option => $value ) {
			if ( ! array_key_exists( $option, $feed_config ) || $feed_config[ $option ] === NULL ) {
				if ( $option === 'user' ) {
					$stored_value = $this->get_db_plugin_option( $this->options_name_main, 'twitter_username' );

				} elseif( $option === 'count' ) {
					$stored_value = $this->get_db_plugin_option( $this->options_name_main, 'result_count' );

				} else {
					$stored_value = $this->get_db_plugin_option( $this->options_name_main, $option );

				}

				if ( $stored_value !== FALSE ) {
					$this->options[ $option ] = $stored_value;
				} else {
					$this->options[ $option ] = $value;
				}

			} elseif ( array_key_exists( $option, $feed_config ) ) {
				$this->options[ $option ] = $feed_config[ $option ];
			}
		}

	/*	The shortcode delivered feed config brings with it
		the 'is_shortcode_called' option */

	/*	As the above check is based on the items in the
		$options property array, this option is ignored
		by the check even if defined in the config by
		the user because it isn't defined in $options */
		if ( isset( $feed_config['is_shortcode_called'] ) && $feed_config['is_shortcode_called'] === TRUE ) {
			$this->is_shortcode_called = TRUE;
		}

	/*	Check to see if there is a cache available with the
		username provided. Move into the output if so */

	/*	However, we don't do this without first checking
		whether or not a clearance of the cache has been
		requested */
		if ( $this->options['clear_cache'] === 'yes' ) {
			$this->clear_cache_output( $this->options['user'] );
		}

		$this->output = get_transient( $this->plugin_name.'_output_'.$this->options['user'] );
		if ( $this->output !== FALSE ) {
			$this->is_cached = TRUE;

		} else {
			$this->is_cached = FALSE;
		}

		// Load the bundled stylesheet if requested
		if ( $this->options['default_styling'] === 'yes' ) {
			$this->load_default_styling();
		}

		// Get Twitter object
		$auth_data = array(
			'oauth_access_token'        => $this->options['oauth_access_token'],
			'oauth_access_token_secret' => $this->options['oauth_access_token_secret'],
			'consumer_key'              => $this->options['consumer_key'],
			'consumer_secret'           => $this->options['consumer_secret']
		);

		$this->twitter = new TwitterAPIExchange( $auth_data );

	}


	/**
	* Based on a limited number of config options, retrieve the raw feed (JSON)
	*
	* @access public
	* @return void
	* @since 1.0.0
	*/
	public function retrieve_feed_data() {

		// Establish the destination point of the request, check for additional params
		$request_method = 'GET';
		$url            = 'https://api.twitter.com/1.1/statuses/user_timeline/'.$this->options['user'].'.json';
		$get_field      = '?screen_name='.$this->options['user'].'&count='.$this->options['count'];

		if ( $this->options['exclude_replies'] === 'yes' ) {
			$get_field .= '&exclude_replies=true';
		}

		$this->feed_data = $this->twitter->setGetfield( $get_field )
		                                 ->buildOauth( $url, $request_method )
		                                 ->performRequest();

		$this->feed_data = json_decode( $this->feed_data );

	}


	/**
	* Check that the timeline queried actually has tweets
	*
	* @access public
	* @return bool An indication of whether or not the returned feed data has any renderable entries
	* @since 1.0.1
	*/
	public function is_empty() {

		if ( is_array( $this->feed_data ) && count( $this->feed_data ) === 0 ) {
			return TRUE;

		} else {
			return FALSE;

		}

	}


	/**
	* Check to see if any errors have been returned when retrieving feed data
	*
	* Be sure to use this only after the retrieve_feed_data() method has been
	* called as that's the method that first populates the $feed_data property
	* that this method checks.
	*
	* @access public
	* @return bool
	* @since 1.0.2
	*/
	public function has_errors() {

	/*	If Twitter's having none of it (most likely due to
		bad config) then we get the errors and display them
		to the user */
		if ( is_object( $this->feed_data ) && is_array( $this->feed_data->errors ) ) {
			$this->errors = $this->feed_data->errors;
			return TRUE;

		} else {
			return FALSE;

		}

	}


	/**
	* Parse and return useful tweet data from an individual tweet in an array
	*
	* This is best utilised within a foreach() loop that iterates through a
	* populated $feed_data.
	*
	* @access public
	* @return array Tweet data from the tweet item given
	* @since 1.0.2
	*/
	public function parse_tweet_data( $t ) {

		$tweet = array();

		$tweet['is_retweet'] = ( isset( $t->retweeted_status ) ) ? TRUE : FALSE;


	/*	User data */
	/************************************************/
		if ( ! $tweet['is_retweet'] ) {
			$tweet['user_id']           = $t->user->id_str;
			$tweet['user_display_name'] = $t->user->name;
			$tweet['user_screen_name']  = $t->user->screen_name;
			$tweet['user_description']  = $t->user->description;
			$tweet['profile_img_url']   = $t->user->profile_image_url;

			if ( isset( $t->user->entities->url->urls ) ) {
				$tweet['user_urls'] = array();
				foreach ( $t->user->entities->url->urls as $url_data ) {
					$tweet['user_urls']['short_url']   = $url_data->url;
					$tweet['user_urls']['full_url']    = $url_data->expanded_url;
					$tweet['user_urls']['display_url'] = $url_data->display_url;
				}
			}


		/*	When Twitter shows a retweet, the account that has
			been retweeted is shown rather than the retweeter's */

		/*	To emulate this we need to grab the necessary data
			that Twitter has thoughtfully made available to us */
		} elseif ( $tweet['is_retweet'] ) {
			$tweet['retweeter_display_name']  = $t->user->name;
			$tweet['retweeter_screen_name']   = $t->user->screen_name;
			$tweet['retweeter_description']   = $t->user->description;
			$tweet['user_id']           = $t->retweeted_status->user->id_str;
			$tweet['user_display_name'] = $t->retweeted_status->user->name;
			$tweet['user_screen_name']  = $t->retweeted_status->user->screen_name;
			$tweet['user_description']  = $t->retweeted_status->user->description;
			$tweet['profile_img_url']   = $t->retweeted_status->user->profile_image_url;

			if ( isset( $t->retweeted_status->url ) ) {
				$tweet['user_urls'] = array();
				foreach ( $t->retweeted_status->user->entities->url->urls as $url_data ) {
					$tweet['user_urls']['short_url']   = $url_data->url;
					$tweet['user_urls']['full_url']    = $url_data->expanded_url;
					$tweet['user_urls']['display_url'] = $url_data->display_url;
				}
			}
		}


	/*	Tweet data */
	/************************************************/
		$tweet['id']				= $t->id_str;
		$tweet['text']				= $t->text;

		if ( (int) $this->options['cache_hours'] <= 2 ) {
			$tweet['date']         = $this->formatify_date( $t->created_at );
		} else {
			$tweet['date']         = $this->formatify_date( $t->created_at, FALSE );
		}

		$tweet['user_replied_to']	= $t->in_reply_to_screen_name;

		$tweet['hashtags'] = array();
		foreach ( $t->entities->hashtags as $ht_data ) {
			$tweet['hashtags']['text'][]    = $ht_data->text;
			$tweet['hashtags']['indices'][] = $ht_data->indices;
		}

		$tweet['mentions'] = array();
		foreach ( $t->entities->user_mentions as $mention_data ) {
			$tweet['mentions'][] = array(
				'screen_name' => $mention_data->screen_name,
				'name'        => $mention_data->name,
				'id'          => $mention_data->id_str
			);
		}

		$tweet['urls'] = array();
		foreach ( $t->entities->urls as $url_data ) {
			$tweet['urls'][] = array(
				'short_url'    => $url_data->url,
				'expanded_url' => $url_data->expanded_url,
				'display_url'  => $url_data->display_url
			);
		}

		if ( isset( $t->entities->media ) ) {
			$tweet['media'] = array();
			foreach ( $t->entities->media as $media_data ) {
				$tweet['media'][] = array(
					'id'           => $media_data->id_str,
					'type'         => $media_data->type,
					'short_url'    => $media_data->url,
					'media_url'    => $media_data->media_url,
					'display_url'  => $media_data->display_url,
					'expanded_url' => $media_data->expanded_url
				);
			}
		}


	/*	Clean up and format the tweet text */
	/************************************************/
		if ( $tweet['is_retweet'] ) {
			// Shave unnecessary "RT [@screen_name]: " from the tweet text
			$char_count  = strlen( '@'.$tweet['user_screen_name'] ) + 5;
			$shave_point = ( 0 - strlen( $tweet['text'] ) ) + $char_count;
			$tweet['text']  = substr( $tweet['text'], $shave_point );
		}

		$tweet['text'] =
		( isset( $tweet['hashtags']['text'] ) ) ? $this->linkify_hashtags( $tweet['text'], $tweet['hashtags']['text'] ) : $tweet['text'];

		$tweet['text'] =
		( isset( $tweet['mentions'] ) ) ? $this->linkify_mentions( $tweet['text'], $tweet['mentions'] ) : $tweet['text'];

		$tweet['text'] =
		( isset( $tweet['urls'] ) ) ? $this->linkify_links( $tweet['text'], $tweet['urls'] ) : $tweet['text'];

		$tweet['text'] =
		( isset( $tweet['media'] ) ) ? $this->linkify_media( $tweet['text'], $tweet['media'] ) : $tweet['text'];

		return $tweet;

	}


	/**
	* Loop through the feed data and render the HTML of the feed
	*
	* The output is stored in the $output property
	*
	* @access public
	* @return void
	* @since 1.0.0
	*/
	public function render_feed_html() {

	/*	If Twitter's having none of it (most likely due to
		bad config) then we get the errors and display them
		to the user */
		if ( $this->has_errors() ) {
			$this->output .= '<pre>Twitter has returned errors:';

			foreach ( $this->feed_data->errors as $error ) {
				$this->output .= '<br />- &ldquo;'.$error->message.' [error code: '.$error->code.']&rdquo;';
			}

			$this->output .= '</pre>';

	/*	If the timeline of the user requested is empty we
		let the user know */
		} elseif( $this->is_empty() ) {
			$this->output .= '<p>Looks like your timeline is completely empty!<br />Why don&rsquo;t you <a href="'.$this->tw.'" target="_blank">login to Twitter</a> and post a tweet or two.</p>';

		// If all is well, we get on with it
		} else {
			$this->output .= '<div class="tweets">';

			foreach ( $this->feed_data as $tweet ) {
				$this->render_tweet_html( $tweet );
			}

			$this->output .= '</div>';

		}

	}


	/**
	* Takes a tweet object and renders the HTML for that tweet
	*
	* The output is stored in the $output property
	*
	* @access public
	* @return void
	* @since 1.0.0
	*/
	public function render_tweet_html( $t ) {

		$tweet = $this->parse_tweet_data( $t );

		// START Rendering the Tweet's HTML (outer tweet wrapper)
		$this->output .= '<article class="tweet">';

		// START Tweet content (inner tweet wrapper)
		$this->output .= '<div class="tweet_content">';

		// START Display pic
		$this->output .= '<figure class="tweet_profile_img">';
		$this->output .= '<a href="'.$this->tw.$tweet['user_screen_name'].'" target="_blank" title="'.$tweet['user_display_name'].'"><img src="'.$tweet['profile_img_url'].'" alt="'.$tweet['user_display_name'].'" /></a>';
		$this->output .= '</figure>';
		// END Display pic

		// START Twitter username/@screen name
		$this->output .= '<header class="tweet_header">';
		$this->output .= '<a href="'.$this->tw.$tweet['user_screen_name'].'" target="_blank" class="tweet_user" title="'.$tweet['user_description'].'">'.$tweet['user_display_name'].'</a>';
		$this->output .= ' <span class="tweet_screen_name">@'.$tweet['user_screen_name'].'</span>';
		$this->output .= '</header>';
		// END Twitter username/@screen name

		// START The Tweet text
		$this->output .= '<div class="tweet_text">'.$tweet['text'].'</div>';
		// END The Tweet text

		// START Tweet footer
		$this->output .= '<div class="tweet_footer">';

		// START Tweet date
		$this->output .= '<a href="'.$this->tw.$tweet['user_screen_name'].'/status/'.$tweet['id'].'" target="_blank" title="View this tweet in Twitter" class="tweet_date">'.$tweet['date'].'</a>';
		// END Tweet date

		// START "Retweeted by"
		if ( $tweet['is_retweet'] ) {
			$this->output .= '<span class="tweet_retweet">';
			$this->output .= '<span class="tweet_icon tweet_icon_retweet"></span>';
			$this->output .= 'Retweeted by ';
			$this->output .= '<a href="'.$this->tw.$tweet['retweeter_screen_name'].'" target="_blank" title="'.$tweet['retweeter_display_name'].'">'.$tweet['retweeter_display_name'].'</a>';
			$this->output .= '</span>';
		}
		// END "Retweeted by"

		// START Tweet intents
		$this->output .= '<div class="tweet_intents">';

		// START Reply intent
		$this->output .= '<a href="'.$this->intent.'tweet?in_reply_to='.$tweet['id'].'" title="Reply to this tweet" target="_blank" class="tweet_intent_reply">';
		$this->output .= '<span class="tweet_icon tweet_icon_reply"></span>';
		$this->output .= '<b>Reply</b></a>';
		// END Reply intent

		// START Retweet intent
		$this->output .= '<a href="'.$this->intent.'retweet?tweet_id='.$tweet['id'].'" title="Retweet this tweet" target="_blank" class="tweet_intent_retweet">';
		$this->output .= '<span class="tweet_icon tweet_icon_retweet"></span>';
		$this->output .= '<b>Retweet</b></a>';
		// END Retweet intent

		// START Favourite intent
		$this->output .= '<a href="'.$this->intent.'favorite?tweet_id='.$tweet['id'].'" title="Favourite this tweet" target="_blank" class="tweet_intent_favourite">';
		$this->output .= '<span class="tweet_icon tweet_icon_favourite"></span>';
		$this->output .= '<b>Favourite</b></a>';
		// END Favourite intent

		$this->output .= '</div>';     // END Tweet intents
		$this->output .= '</div>';     // END Tweet footer
		$this->output .= '</div>';     // END Tweet content
		$this->output .= '</article>'; // END Rendering Tweet's HTML

	}


	/*	The following "linkify" functions look for
		specific components within the tweet text
		and converts them to links using the data
		provided by Twitter */

	/*	@Mouthful
		Each function accepts an array holding arrays.
		Each array held within the array represents
		an instance of a linkable item within that
		particular tweet and has named keys
		representing useful data to do with that
		instance of the linkable item */

	/*	It's slightly different for the hashtags but
		I can't remember why */
	/************************************************/
	public function linkify_hashtags( $tweet, $hashtags ) {

		$search = 'https://twitter.com/search?q=%23';

		if ( $hashtags !== NULL ) {
			foreach ( $hashtags as $hashtag ) {
				$tweet = str_replace(
					'#'.$hashtag,
					'<a href="'.$search.$hashtag.'" target="_blank" title="Search Twitter for \''.$hashtag.'\' ">#'.$hashtag.'</a>',
					$tweet
				);
			}

			return $tweet;

		} else {
			return $tweet;

		}

	}

	public function linkify_mentions( $tweet, $mentions ) {

		$twitter = 'https://twitter.com/';

		if ( is_array( $mentions ) && count( $mentions ) !== 0 ) {
			foreach ( $mentions as $mention ) {
				$count = count( $mentions );

				for ( $i = 0; $i < $count; $i++ ) {
					$tweet = preg_replace(
						'|@'.$mentions[ $i ]['screen_name'].'|',
						'<a href="'.$twitter.$mentions[ $i ]['screen_name'].'" target="_blank" title="'.$mentions[ $i ]['name'].'">@'.$mentions[ $i ]['screen_name'].'</a>',
						$tweet
					);
				}

				return $tweet;
			}

		} else {
			return $tweet;

		}

	}

	public function linkify_links( $tweet, $urls ) {

		if ( is_array( $urls ) && count( $urls ) !== 0 ) {
			foreach ( $urls as $url ) {
				$count = count( $urls );

				for ( $i = 0; $i < $count; $i++ ) {
					$tweet = str_replace(
						$urls[ $i ]['short_url'],
						'<a href="'.$urls[ $i ]['short_url'].'" target="_blank">'.$urls[ $i ]['display_url'].'</a>',
						$tweet
					);
				}

				return $tweet;
			}

		} else {
			return $tweet;

		}

	}

	public function linkify_media( $tweet, $media ) {

		if ( is_array( $media ) && count( $media ) !== 0 ) {
			foreach ( $media as $item ) {
				$count = count( $media );

				for ( $i = 0; $i < $count; $i++ ) {
					$tweet = str_replace(
						$media[ $i ]['short_url'],
						'<a href="'.$media[ $i ]['short_url'].'" target="_blank">'.$media[ $i ]['display_url'].'</a>',
						$tweet
					);
				}

				return $tweet;
			}

		} else {
			return $tweet;

		}

	}


	/**
	* Echo whatever is currently stored in the DB_Twitter_Feed::output property to the page
	*
	* This method also calls the DevBuddy_Feed_Plugin::cache_output() method
	*
	* @access public
	* @return void
	* @uses DevBuddy_Feed_Plugin::cache_output() to cache the output before it's echoed
	*
	* @since 1.0.0
	*/
	public function echo_output() {

		$this->cache_output( $this->options['cache_hours'] );
		echo $this->output;

	}
} // END class

} // END class_exists

?>