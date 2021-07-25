<!DOCTYPE html>
<script src='https://code.responsivevoice.org/responsivevoice.js'>
</script>

<html>
<head>
    <meta charset="utf-8" />
    <title></title>
</head>
<body>
    <h1>Audio</h1>

    <button id="startRecordingButton">Start recording</button>
    <button id="stopRecordingButton">Stop recording</button>

    <script src="https://code.jquery.com/jquery-3.2.1.min.js"></script>

    <script>
        var startRecordingButton = document.getElementById("startRecordingButton");
        var stopRecordingButton = document.getElementById("stopRecordingButton");


        var leftchannel = [];
        var rightchannel = [];
        var recorder = null;
        var recordingLength = 0;
        var volume = null;
        var mediaStream = null;
        var sampleRate = 44100;
        var context = null;
        var blob = null;

        startRecordingButton.addEventListener("click", function () {
            alert("started...")
            // Initialize recorder
            navigator.getUserMedia = navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia;
            navigator.getUserMedia(
            {
                audio: true
            },
            function (e) {
                console.log("user consent");

                // creates the audio context
                window.AudioContext = window.AudioContext || window.webkitAudioContext;
                context = new AudioContext();

                // creates an audio node from the microphone incoming stream
                mediaStream = context.createMediaStreamSource(e);

                // https://developer.mozilla.org/en-US/docs/Web/API/AudioContext/createScriptProcessor
                // bufferSize: the onaudioprocess event is called when the buffer is full
                var bufferSize = 2048;
                var numberOfInputChannels = 2;
                var numberOfOutputChannels = 2;
                if (context.createScriptProcessor) {
                    recorder = context.createScriptProcessor(bufferSize, numberOfInputChannels, numberOfOutputChannels);
                } else {
                    recorder = context.createJavaScriptNode(bufferSize, numberOfInputChannels, numberOfOutputChannels);
                }

                recorder.onaudioprocess = function (e) {
                    leftchannel.push(new Float32Array(e.inputBuffer.getChannelData(0)));
                    rightchannel.push(new Float32Array(e.inputBuffer.getChannelData(1)));
                    recordingLength += bufferSize;
                }

                // we connect the recorder
                mediaStream.connect(recorder);
                recorder.connect(context.destination);
            },
                        function (e) {
                            console.error(e);
                        });
        });

        stopRecordingButton.addEventListener("click", function () {
            alert("Stopped...")
            document.write("<h1> Uploading.... </h1>");

            // stop recording
            recorder.disconnect(context.destination);
            mediaStream.disconnect(recorder);

            // we flat the left and right channels down
            // Float32Array[] => Float32Array
            var leftBuffer = flattenArray(leftchannel, recordingLength);
            var rightBuffer = flattenArray(rightchannel, recordingLength);
            // we interleave both channels together
            // [left[0],right[0],left[1],right[1],...]
            var interleaved = interleave(leftBuffer, rightBuffer);

            // we create our wav file
            var buffer = new ArrayBuffer(44 + interleaved.length * 2);
            var view = new DataView(buffer);

            // RIFF chunk descriptor
            writeUTFBytes(view, 0, 'RIFF');
            view.setUint32(4, 44 + interleaved.length * 2, true);
            writeUTFBytes(view, 8, 'WAVE');
            // FMT sub-chunk
            writeUTFBytes(view, 12, 'fmt ');
            view.setUint32(16, 16, true); // chunkSize
            view.setUint16(20, 1, true); // wFormatTag
            view.setUint16(22, 2, true); // wChannels: stereo (2 channels)
            view.setUint32(24, sampleRate, true); // dwSamplesPerSec
            view.setUint32(28, sampleRate * 4, true); // dwAvgBytesPerSec
            view.setUint16(32, 4, true); // wBlockAlign
            view.setUint16(34, 16, true); // wBitsPerSample
            // data sub-chunk
            writeUTFBytes(view, 36, 'data');
            view.setUint32(40, interleaved.length * 2, true);

            // write the PCM samples
            var index = 44;
            var volume = 1;
            for (var i = 0; i < interleaved.length; i++) {
                view.setInt16(index, interleaved[i] * (0x7FFF * volume), true);
                index += 2;
            }

            // our final blob
            blob = new Blob([view], { type: 'audio/wav' });
            uploadAudio(blob)

        });

        function uploadAudio( blob ) {
          // alert(blob)
          var reader = new FileReader();
          reader.onload = function(event){
            var fd = {};
            fd["fname"] = "sample.wav"; // Change this file name with user - ID
            fd["data"] = event.target.result;
            $.ajax({
              type: 'POST',
              url: 'upload_file.php',
              data: fd,
              dataType: 'text'
            }).done(function(obj) {
                document.write("<h1>"+obj+"</h1>");
                var txt = "";
                const data = JSON.parse(obj);
                                
                if(data["intent"] === "greeting"){
                	txt = "hello user how can I help you"
					responsiveVoice.speak(txt);
                }
                else if(data["intent"] === "bookBuy"){
                	txt = "yes we do have "+data["value"]+"book"
					responsiveVoice.speak(txt);
                }
                else if(data["intent"] === "bookName"){
                	txt = "There are many books present in the database. Try saying a particular name."
					responsiveVoice.speak(txt);
                }	
                else if(data["intent"] === "bookPrice"){
                	txt = "yes we do have "+data["value"]+"book and it costs "+data["data"]+"rupees"
					responsiveVoice.speak(txt);
                }               
                else if(data["intent"] === "buy"){
                	txt = "You are buying "+data["value"]+"book and it costs "+data["price"]+"rupees"
					responsiveVoice.speak(txt);
                }
                else if(data["intent"] === "follow"){
                	txt = "Now you are following"+data["value"]
					responsiveVoice.speak(txt);
                }
                else if(data["intent"] === "friendlist"){
                	txt = "Wow you have got lots of friends"
					responsiveVoice.speak(txt);
                }
                else{
                	txt = "sorry I did not understand you"
					responsiveVoice.speak(txt);
                }


            });
          };
          reader.readAsDataURL(blob);

        }
                        
        function flattenArray(channelBuffer, recordingLength) {
            var result = new Float32Array(recordingLength);
            var offset = 0;
            for (var i = 0; i < channelBuffer.length; i++) {
                var buffer = channelBuffer[i];
                result.set(buffer, offset);
                offset += buffer.length;
            }
            return result;
        }

        function interleave(leftChannel, rightChannel) {
            var length = leftChannel.length + rightChannel.length;
            var result = new Float32Array(length);

            var inputIndex = 0;

            for (var index = 0; index < length;) {
                result[index++] = leftChannel[inputIndex];
                result[index++] = rightChannel[inputIndex];
                inputIndex++;
            }
            return result;
        }

        function writeUTFBytes(view, offset, string) {
            for (var i = 0; i < string.length; i++) {
                view.setUint8(offset + i, string.charCodeAt(i));
            }
        }

    </script>


</body>
</html>
