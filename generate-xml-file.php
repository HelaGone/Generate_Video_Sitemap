<?php 
	/**
	 * Plugin Name: Generate XML File
	 * Plugin URI:  https://cubeinthebox.com
	 * Description: Generate xml files for google video sitemap
	 * Version:     1.0.0
	 * Author:      Holkan Luna
	 * Author URI:  https://cubeinthebox.com/
	 * License:     GPL2
	 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
	 * Text Domain: xml-file
	 * Domain Path: /languages
	 */

	function gxf_activation_fn(){
		
	}	
	register_activation_hook( __FILE__, 'gxf_activation_fn' );


	function gxf_deactivation_fn(){
		if(is_file('../video_sitemap.xml')){
			unlink('../video_sitemap.xml');
		}
	}
	register_deactivation_hook( __FILE__, 'gxf_deactivation_fn' );

	function gxf_count_video_posts($post_id, $post){

		if('video'===get_post_type($post_id)){
			$args = array(
				'post_type'=>'video',
				'posts_per_page'=>5,
				'post_status'=>'publish',
				'orderby'=>'date',
				'order'=>'DESC'
			);
			$videos = get_posts($args);
			gxf_generate_xml_file($videos);
		}

	}
	add_action('save_post', 'gxf_count_video_posts', 10, 2);

	function gxf_generate_xml_file($posts_object){
		$xml = new DOMDocument('1.0', 'UTF-8');

		$urlset = $xml->createElement('urlset');
		$urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
		$urlset->setAttribute('xmlns:video', 'http://www.google.com/schemas/sitemap-video/1.1');

		$xml->appendChild($urlset);

		foreach ($posts_object as $p_object) {
			$vid_permalink = get_the_permalink($p_object->ID);
			$vid_post_thumbnail_url = get_the_post_thumbnail_url($p_object->ID);
			$anvato_id = get_post_meta($p_object->ID, 'id_anvato', true);
			$youtube_id = get_post_meta($p_object->ID, 'id_youtube', true);
			$vid_publish_date = $p_object->post_date_gmt;

			$anv_title = '';
			$anv_duration = '';
			$anv_description = '';
			$anv_media_url = '';
			$anv_thumbnail = '';
			$anv_height = '';
			$anv_width = '';

			if(gettype($anvato_id)==='string'&&$anvato_id!==''){
				$anv_data = gxf_get_video_data($anvato_id);

				foreach($anv_data['docs'] as $key => $value){
					$anv_title = $value['c_title_s'];
					$anv_duration = $value['info']['duration'];
					$anv_description = $value['c_description_s'];
					$anv_media_url = $value['media_url'];
					$anv_thumbnail = $value['thumbnails'][0]['url'];
					$anv_height = $value['thumbnails'][0]['height'];
					$anv_width = $value['thumbnails'][0]['width'];
					$anv_pub_date = gmdate('Y-m-d\TH:i:s\-06:00', $value['c_ts_publish_l']);

					$anv_object = array('url'=>$anv_media_url,'poster'=>$anv_thumbnail,'title'=>$anv_title,'description'=>$anv_description);
					$anv_player_url = gxf_get_anvato_player(json_encode($anv_object));


					$url = $xml->createElement('url');
					$url->appendChild($xml->createElement('loc', $vid_permalink));

					$video_node = $xml->createElement('video:video');
					$video_node->appendChild($xml->createElement('video:thumbnail_loc', $vid_post_thumbnail_url));

					$video_title = $xml->createElement('video:title');
					$video_title->appendChild($xml->createCDATASection(esc_html($p_object->post_title)));
					$video_node->appendChild($video_title);

					$video_description = $xml->createElement('video:description');
					$video_description->appendChild($xml->createCDATASection(esc_html($p_object->post_excerpt)));
					$video_node->appendChild($video_description);

					$video_node->appendChild($xml->createElement('video:content_loc', htmlspecialchars($anv_media_url)));
					$video_node->appendChild($xml->createElement('video:player_loc', htmlspecialchars($anv_player_url)));
					$video_node->appendChild($xml->createElement('video:duration', $anv_duration));
					$video_node->appendChild($xml->createElement('video:publication_date', $anv_pub_date));
					$video_node->appendChild($xml->createElement('video:family_friendly', 'yes'));

					$video_restriction = $xml->createElement('video:restriction', 'US');
					$video_restriction->setAttribute('relationship', 'deny');
					$video_node->appendChild($video_restriction);

					$video_node->appendChild($xml->createElement('video:live', 'no'));

					$url->appendChild($video_node);

					$urlset->appendChild($url);

				}
			}else if(gettype($youtube_id)==='string'&&$youtube_id!==''){
				$yt_vid_url = 'https://www.youtube.com/watch?v='.$youtube_id;
				$millis = time($vid_publish_date);
				$pubDate = gmdate('Y-m-d\TH:i:s\-06:00', $millis);
				$duration = gxf_get_youtube_duration($youtube_id);


				$url = $xml->createElement('url');
				$url->appendChild($xml->createElement('loc', $vid_permalink));

				$video_node = $xml->createElement('video:video');
				$video_node->appendChild($xml->createElement('video:thumbnail_loc', $vid_post_thumbnail_url));

				$video_title = $xml->createElement('video:title');
				$video_title->appendChild($xml->createCDATASection(esc_html($p_object->post_title)));
				$video_node->appendChild($video_title);

				$video_description = $xml->createElement('video:description');
				$video_description->appendChild($xml->createCDATASection(esc_html($p_object->post_excerpt)));
				$video_node->appendChild($video_description);

				$video_node->appendChild($xml->createElement('video:content_loc', htmlspecialchars($yt_vid_url)));
				$video_node->appendChild($xml->createElement('video:player_loc', htmlspecialchars($yt_vid_url)));
				$video_node->appendChild($xml->createElement('video:duration', $duration));
				$video_node->appendChild($xml->createElement('video:publication_date', $pubDate));
				$video_node->appendChild($xml->createElement('video:family_friendly', 'yes'));

				$video_restriction = $xml->createElement('video:restriction', 'US');
				$video_restriction->setAttribute('relationship', 'deny');
				$video_node->appendChild($video_restriction);

				$video_node->appendChild($xml->createElement('video:live', 'no'));

				$url->appendChild($video_node);

				$urlset->appendChild($url);
			}
		}

		$xml->save('../video_sitemap.xml');
	}

	function gxf_get_video_data($videoId){
		$requestUrl = 'https://api.anvato.net/v2/feed/K2KWCWUDQ5YXI2BNEFDWAAA?filters[]=obj_id:'.$videoId;
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $requestUrl);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 500);
		curl_setopt($curl, CURLOPT_TIMEOUT, 500);
		$response = curl_exec($curl);
		curl_close($curl);
		$result = json_decode($response, true);
		return $result;
	}

	function gxf_get_anvato_player($json){
		$decoded = json_decode($json);
		$iframe_src = 'https://w3.cdn.anvato.net/player/prod/v3/anvload.html?key=' . base64_encode( json_encode( $decoded ) );
		return $iframe_src;
	}

	function gxf_get_youtube_duration($videoId){
		$apikey = 'AIzaSyC6zVkEIkrXXsvuAy5Z0QiDQld-ZPz1zVI';
		$dur = file_get_contents('https://www.googleapis.com/youtube/v3/videos?part=contentDetails&id='.$videoId.'&key='.$apikey);
		$vid_duration = json_decode($dur, true);
		$duration = '';
		foreach ($vid_duration['items'] as $vidTime) {
			$duration = $vidTime['contentDetails']['duration'];
		}
		return gfx_ISO8601ToSeconds($duration);
	}

	function gfx_ISO8601ToSeconds($ISO8601){
    	$interval = new \DateInterval($ISO8601);
    	return ($interval->d * 24 * 60 * 60) + ($interval->h * 60 * 60) + ($interval->i * 60) + $interval->s;
	}

?>