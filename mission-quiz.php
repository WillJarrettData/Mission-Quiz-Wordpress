<?php
/*
 * Plugin Name:     MissionQuiz
 * Plugin URI:      https://github.com/Firmware-Repairman/Mission-Quiz-Wordpress
 * Description:     Quiz template that tracks totals of user answers. Used in missionlocal.org
 * Version:         1.0
 * Author:          Craig Mautner
 * License:         GPLv3
 * License URI:     https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Copyright 2023 Craig Mautner (mailto:craig.mautner@gmail.com)
 *
 * This file is part of the wordpress plugin MissionQuiz.
 *
 * MissionQuiz is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option)
 * any later version.
 *
 * MissionQuiz is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * MissionQuiz. If not, see <https://www.gnu.org/licenses/>.
 */

if (!class_exists("MissionQuiz"))
{
    global $mission_quiz_db_version;
    $mission_quiz_db_version = "1.0";

    class MissionQuiz
    {
        function __construct()
        {
            add_action( 'init', array( &$this, 'init_plugin' ));
            add_action( 'wp_ajax_update_answers', array( &$this, 'ajax_update_answers' ));
            add_action( 'wp_ajax_nopriv_update_answers', array( &$this, 'ajax_update_answers' ));
            add_action( 'wp_head', array( &$this, 'add_ajax_url' ));
        }

        // Called on activation or update
        public function setup_plugin() {
            global $wpdb;
            global $mission_quiz_db_version;
            require_once( ABSPATH . 'wp-admin/upgrade-functions.php' );

            // if the option is already set then setup not required
            if (get_option("mission_quiz_db_version")) {
                if (get_option("mission_quiz_db_version") != $mission_quiz_db_version) {
                    die ("downgrade not supported!!!"); // there is no prev version using this var
                }
                return;
            }

            // create the database, will fail if it already exists
            if ( !empty($wpdb->charset) )
                $charset_collate = "DEFAULT CHARACTER SET ".$wpdb->charset;
            $sql[] = "CREATE TABLE ".$wpdb->base_prefix."mission_quiz_answer_totals (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    quiz_id varchar(32) NOT NULL,
                    question_id bigint(20) NOT NULL,
                    answer varchar(32) NOT NULL,
                    answer_total bigint(20) NOT NULL DEFAULT 1,
                    KEY answer_id (quiz_id, question_id, answer),
                    CONSTRAINT UNIQUE (quiz_id, question_id, answer)
                ) ".$charset_collate.";";

            $result = dbDelta ($sql);

            // set the option to skip setup next time
            update_option ("mission_quiz_db_version", $mission_quiz_db_version);
        }

        public function init_plugin() {
            if ( !is_admin() ) {
                // load the style sheet
                wp_register_style( 'mission-quiz', plugins_url( '/style/mission-quiz.css', __FILE__));
                wp_enqueue_style( 'mission-quiz' );

                // load the javascript
                wp_register_script( 'mission-quiz',
                        plugins_url( '/js/mission-quiz.js', __FILE__),
                        array( 'jquery' ),
                        '1.0'
                    );
                 wp_enqueue_script('mission-quiz');
            }
        }

        public function add_ajax_url()
        {
            echo '<script type="text/javascript">var MissionQuiz = { ajaxurl: "'.admin_url ('admin-ajax.php').'" };</script>';
        }

        //********************************************************************
        // Ajax handler, called when javascript calls jQuery.post(MissionQuiz.ajaxurl, data, ...)
        // with data.action='update_answers'
        public function ajax_update_answers() {
            global $wpdb;  // database handle

            // return object. Will be passed to the jQuery callback as json
            $result = array( 'status' => '-1',
                             'message' => '',
                             'percent_answered' => [] );

            //Validate expected params
            if ( $_POST['quiz_id'] == null ) {
                $result["message"] = "ajax_update_answers: quiz_id was null";
                die(json_encode($result));
            }
            if ( $_POST['question_no'] == null ) {
                $result["message"] = "ajax_update_answers: question_no was null";
                die(json_encode($result));
            }
            if ( $_POST['answer_no'] == null ) {
                $result["message"] = "ajax_update_answers: answer_no was null";
                die(json_encode($result));
            }

            $quiz_id = $_POST['quiz_id'];
            $question_no = $_POST['question_no'];
            $answer_no = $_POST['answer_no'];

            //Update user vote, $answer_no=-1 means no answer selected
            if ($answer_no >= 0) {
                // Either create a new entry (answer_total defaults to 1) or increment the
                // existing entry (UPDATE answer_total = (answer_total + 1);)
                $wpdb->query($wpdb->prepare("
                    INSERT INTO ".$wpdb->base_prefix."mission_quiz_answer_totals
                        (quiz_id, question_id, answer)
                        VALUES (%s, %d, %d)
                        ON DUPLICATE KEY UPDATE answer_total = (answer_total + 1);",
                        $quiz_id, $question_no, $answer_no));
            }

            // Retrieve all of the answer_totals for this quiz/question/answer combination
            $answer_totals = $wpdb->get_results($wpdb->prepare("
                SELECT answer, answer_total FROM ".$wpdb->base_prefix."mission_quiz_answer_totals
                    WHERE quiz_id=%s AND question_id=%d
                    ORDER BY answer ASC;",
                $quiz_id, $question_no));

            //Return success
            $result["status"] = 1;
            $result["message"] = "All good";
            $result["percent_answered"] = $answer_totals;

            die(json_encode($result));
        }
    } //class:MissionQuiz

    //Create instance of plugin
    $mission_quiz_plugin = new MissionQuiz();

    //Handle plugin activation and update
    register_activation_hook( __FILE__, array( &$mission_quiz_plugin, 'setup_plugin' ));
    if (function_exists ("register_update_hook"))
        register_update_hook ( __FILE__, array( &$mission_quiz_plugin, 'setup_plugin' ));
    else
        add_action('init', array( &$mission_quiz_plugin, 'setup_plugin' ), 1);


/* Modify myQuestions[] below, then add the following to your Wordpress page using the HTML widget.
<!--  BEGIN quiz HTML -->


    <div class="outer">
        <div class="quiz-container" id="jump-to-next">
            <div id="quiz"></div>
            <div id="explanation"></div>
            <div class="button-container">
                <button id="next" class="button1 hide" onclick="plusSlides(1)">Next</button>
                <div id="results"></div>
            </div>
        </div>
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>

    <script>
    ////
    //// QUIZ DATA
    ////
    const myQuestions = [
        {
        "number": "1",
        "question":"Which Mission personality is leaving San Francisco?",
        "image":"https://missionloca.s3.amazonaws.com/mission/wp-content/uploads/2022/07/placeholder.jpg",
        "answers": {a:"Lone Star Swan",b:"Manny Yekutiel",c:"The Crème Brûlée Cart Man"},
        "correctAnswer":"The Crème Brûlée Cart Man",
        "explanation": "Curtis Kimball, also known as the The Crème Brûlée Cart Man/The Pancake Guy, is <a target='_blank' href='https://missionlocal.org/2022/07/creme-brulee-cart-pancake-curtis-kimball/'>leaving San Francisco after two decades to be closer to ailing family</a>. He said goodbye with a final pancake event on Saturday.<br /><br />Sadly, <a target='_blank' href='https://missionlocal.org/2022/02/swan-song-john-ratliff-lone-star-swan-dies-at-81/'>Lone Star Swan</a> died earlier this year."
        },
        {
        "number": "2",
        "question":"Gig Workers Rising is the biggest group trying to organize gig workers in Northern California. Roughly how many members do they have?",
        "image":"https://missionloca.s3.amazonaws.com/mission/wp-content/uploads/2022/07/featured-image-1-1536x1184.jpg",
        "answers": {a:"20",b:"200",c:"2,000"},
        "correctAnswer":"200",
        "explanation": "Volunteer organizers have been trying to <a target='_blank' href='https://missionlocal.org/2022/07/labor-organizers-sf-gig-workers-tough-target/'>reach out to gig workers</a> to ultimately claim the right to strike, protest, and make demands of employers. Gig workers have far fewer protections than regular employees and have not been able to organize unions in the same way."
        },
        {
        "number": "3",
        "question":"When District Attorney Brooke Jenkins gave a speech in Chinatown, the event’s translator took some liberties with their Cantonese translation. What did the translator say?",
        "image":"https://missionloca.s3.amazonaws.com/mission/wp-content/uploads/2022/07/IMG_9868.jpg",
        "answers": {a:"Jenkins will bring “bandits” to justice",b:"Jenkins will continue the legacy of Chesa Boudin",c:"Jenkins will pursue responsible reform"},
        "correctAnswer":"Jenkins will bring “bandits” to justice",
        "explanation": "<a target='_blank' href='https://missionlocal.org/2022/07/da-brooke-jenkins-chinatown-scourge-anti-asisan-bandits/'>Jenkins said</a> “no longer will we just allow people to walk around feeling like they’re going to be targeted because of who they are,” which was translated into a promise to “bring all the bandits who attacked our Asians and attacked our community to justice.”"
        },
        {
        "number": "4",
        "question":"Why were transfers from Laguna Honda hospital halted yesterday?",
        "image":"https://missionloca.s3.amazonaws.com/mission/wp-content/uploads/2022/05/Screen-Shot-2022-05-17-at-2.55.12-PM-1536x960.png",
        "answers": {a:"Patients’ families staged a hunger strike",b:"Other facilities refused to take patients",c:"Several patients died after moving"},
        "correctAnswer":"Several patients died after moving",
        "explanation": "Pleas from families and advocates to <a target='_blank' href='https://missionlocal.org/2022/07/transfers-from-laguna-honda-put-on-hold-but-not-before-at-least-five-patient-deaths/'>halt patient transfers</a> out of Laguna Honda Hospital were heard after at least five patients died right after they were transfered."
        },
        {
        "number": "5",
        "question":"Some 300 workers from which SF housing manager went on strike this Wednesday?",
        "image":"https://missionloca.s3.amazonaws.com/mission/wp-content/uploads/2022/07/Strike_banner_blurred.jpg",
        "answers": {a:"Tenderloin Neighborhood Development Corporation",b:"Tenderloin Housing Clinic",c:"Caritas Management"},
        "correctAnswer":"Tenderloin Housing Clinic",
        "explanation": "The workers were <a target=’_blank’ href=’https://missionlocal.org/2022/07/tenderloin-housing-clinic-workers-walk-off-job-demand-living-wage/’>demanding higher wages</a> for all of their staff. The Tenderloin Housing Clinic management said that the wages they can give are dependent on money provided by the city."
        },
        {
        "number": "6",
        "question":"How did Mission resident Anand Upender get to know his neighbors?",
        "image":"https://missionloca.s3.amazonaws.com/mission/wp-content/uploads/2022/07/placeholder.jpg",
        "answers": {a:"Launched an oral history project",b:"Served coffee out of his garage",c:"Performed 12 hours of interpretive dance"},
        "correctAnswer":"Served coffee out of his garage",
        "explanation": "Upender and his roommates didn’t want to be “the kind of transient young people who move through cities” quickly, he said, prompting his <a target='_blank' href=https://missionlocal.org/2022/07/york-street-coffee-pop-up-mission/''>coffee-based community building</a>."
        },
        {
        "number": "7",
        "question":"Who created this linoleum print, featured in a recently opened Richmond exhibition?",
        "image":"https://missionloca.s3.amazonaws.com/mission/wp-content/uploads/2022/07/My-project-3-11-612x640.png",
        "answers": {a:"Diego Rivera",b:"Frida Kahlo",c:"Emmy Lou Packard"},
        "correctAnswer":"Emmy Lou Packard",
        "explanation": "Packard was a friend and ally of Mexican muralist Diego Rivera and his wife Frida Kahlo, but produced a <a target='_blank' href=’https://missionlocal.org/2022/07/who-is-emmy-lou-packard/'>plenty of her own work too</a>. Some of her best works will be on display at the Richmond Arts Center until August 20."
        },
    ];
    ////
    //// INITIALIZE
    ////
    // grab quiz HTML and build the quiz
    const quizContainer = document.getElementById('quiz');
    buildQuiz();

    var post_id = 0;
    classes = document.body.classList;
    for (let i in classes) {
        if (classes[i].startsWith("postid")) {
            post_id = classes[i].split("-")[1];
            break;
        }
    }
    if (post_id === 0) {
        alert("post_id not found");
    }

    questionIterate = -1;
    numCorrect = 0;
    // on answer click
    $(".button-answers").click(function() {
        $('#next').removeClass('hide');
        var userAnswer = $(this).attr('id');
        var correctAnswer = myQuestions[questionIterate].correctAnswer;
        var userAnswerButton = document.getElementById(userAnswer);
        var correctAnswerButton = document.getElementById(correctAnswer);
        validateAnswers(userAnswer, correctAnswer, userAnswerButton, correctAnswerButton);
    });
    // start off slides
    let slideIndex = 0;
    showSlides(slideIndex);

    </script>

*/

}

?>
