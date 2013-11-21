<?php

/*
Once you've initialised the feed using `new DB_Twitter_Feed` the feed's configuration options
will be available to you as an array. The defaults of which are shown below:

$the_feed->options['user']                      => 'EjiOsigwe' // String: Any valid Twitter username
$the_feed->options['count']                     => '10'        // String: Number of tweets to retrieve
$the_feed->options['exclude_replies']           => 'no'        // String: ("yes" or "no") Only display tweets that aren't replies
$the_feed->options['default_styling']           => 'no'        // String: ("yes" or "no") Load the bundled stylesheet
$the_feed->options['cache_hours']               => 0           // Int:    Number of hours to cache the output
$the_feed->options['clear_cache']               => 'no'        // String: ("yes" or "no") Clear the cache for the set "user"
$the_feed->options['oauth_access_token']        => NULL        // String: The OAuth Access Token
$the_feed->options['oauth_access_token_secret'] => NULL        // String: The OAuth Access Token Secret
$the_feed->options['consumer_key']              => NULL        // String: The Consumer Key
$the_feed->options['consumer_secret']           => NULL        // String: The Consumer Secret


There are also some Twitter specific links available too:
$the_feed->tw     = 'https://twitter.com/';
$the_feed->search = 'https://twitter.com/search?q=%23';
$the_feed->intent = 'https://twitter.com/intent/';

Any other useful methods and properties are used and commented on in the template tag below.
****************************************************************************************** */



// Copy this entire function into your theme
// Read through the comments and edit as you see fit
function my_twitter_feed_template_tag( $feed_config = NULL ) {

	// Configuration validity checks are performed on instantiation
	$the_feed = new DB_Twitter_Feed( $feed_config );


	// We only want to talk to Twitter when our cache is on empty
	if ( ! $the_feed->is_cached ) {

		// Makes a request to Twitter for tweet data based on $the_feed->options
		$the_feed->retrieve_feed_data();


		// After attempting data retrieval, check for errors
		// Feel free to change the error message
		if ( $the_feed->has_errors() ) {
			$the_feed->output .= '<p>Twitter has returned errors</p>';

			// Uncomment the following code to see the details of errors
			/*$the_feed->output .= '<ul>';

			foreach ( $the_feed->errors as $error ) {
				$the_feed->output .= '<li>&ldquo;'.$error->message.' [error code: '.$error->code.']&rdquo;</li>';
			}

			$the_feed->output .= '</ul>';
			$the_feed->output .= '<p>More information on errors <a href="https://dev.twitter.com/docs/error-codes-responses" target="_blank" title="Twitter API Error Codes and Responses">here</a>.</p>';*/


		// Then check for an empty timeline
		// Feel free to change the notification message
		} elseif( $the_feed->is_empty() ) {
			$the_feed->output .= '<p>Looks like your timeline is completely empty!<br />Why don&rsquo;t you <a href="'.$this->tw.'" target="_blank">login to Twitter</a> and post a tweet or two.</p>';


		// If all is well we can get to HTML renderin'
		} else {

			// START The Tweet list
			$the_feed->output .= '<div class="tweets">';

			foreach ( $the_feed->feed_data as $tweet ) {

				$the_feed->html->set( $the_feed->parse_tweet_data( $tweet ) );


			/* Below is the default HTML layout.

			   The HTML class writes the actual HTML, just move the
			   parts around as needed.

			   If you do move things around, be sure to update your
			   stylesheet accordingly.

			   NOTE: The primary and secondary meta HTML parts are
			   merely generic div elements used as content wrappers.
			*********************************************************/

				// START Rendering the Tweet's HTML (outer tweet wrapper)
				$the_feed->output .= $the_feed->html->open_tweet();


					// START Tweet content (inner tweet wrapper)
					$the_feed->output .= $the_feed->html->open_tweet_content();


						// START Tweeter's display picture
						$the_feed->output .= $the_feed->html->tweet_display_pic();
						// END Tweeter's display picture


						// START Tweet user info
						$the_feed->output .= $the_feed->html->open_tweet_primary_meta();
							$the_feed->output .= $the_feed->html->tweet_display_name_link();
						$the_feed->output .= $the_feed->html->close_tweet_primary_meta();
						// END Tweet user info


						// START Actual tweet
						$the_feed->output .= $the_feed->html->tweet_text();
						// END Actual tweet


						// START Tweet meta data
						$the_feed->output .= $the_feed->html->open_tweet_secondary_meta();
							$the_feed->output .= $the_feed->html->tweet_date();
							$the_feed->output .= $the_feed->html->tweet_retweeted();
							$the_feed->output .= $the_feed->html->tweet_intents();
						$the_feed->output .= $the_feed->html->close_tweet_secondary_meta();
						// END Tweet meta data


					$the_feed->output .= $the_feed->html->close_tweet_content();
					// END Tweet content


				$the_feed->output .= $the_feed->html->close_tweet();
				// END Rendering Tweet's HTML

				$the_feed->cache_output( $the_feed->options['cache_hours'] );

			} // END looping through tweet data

			$the_feed->output .= '</div>';
			// END The Tweet list

		}

	} // END cache check


	/* WP needs shortcode called content to be returned
	   rather than echoed, which is where the
	   $is_shortcode_called property comes in */
	if ( $the_feed->is_shortcode_called ) {
		return $the_feed->output;
	} else {
		echo $the_feed->output;
	}

}