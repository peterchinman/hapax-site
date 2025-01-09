<!DOCTYPE html>
<html lang="en">
<head>

    <!-- Adding this base to try to fix linking when this gets included as subdirectory on peterchinman.com -->
    <?php
    // Determine the environment
    $isLocal = ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1');

    // Set the base URL accordingly
    $baseHref = $isLocal ? '/' : '/hapax-finder/';
    ?>

    <base href="<?= $baseHref ?>" />

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hapax Legomenon Finder</title>

    <meta name="description" content="Find Hapax Legomena from the safety of your browser.">
    <meta property="og:image" content="static/sharecard.jpg">

    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <header>
        <hr class="
        top-bar">
        <div class="title">Hapax
Legomenon
Finder</div>
        <div class="description">
            <p>A <em>hapax legomenon</em> (abbreviated to <em>hapax</em>, pl. <em>hapax legomena</em>) is a word that's used only once in a body of text. In the translation of dead languages, hapaxes are the prickly outliers—words that come with only a single context from which to infer their meaning.</p>
            <p>I'm interested in these the prickly points. The silhouette of a vocabulary.</p>
            <p><a href="https://peterchinman.com/blog/hapax-finder">Read about coding this project.</a></p>
        </div>
        <hr class="bottom-bar">
    </header>
    <main>
        <section class="examples">
            <h1 class="assertive">Explore hapaxes from these texts:</h1>
            
            <ul>
                <?php
                    include 'examples.php';
                    foreach ($examples as $example) {
                ?>
                <li>
                    <a href="#" onclick="fetchHapaxes('<?=$example['file-name']?>-hapaxes.txt'); return false;">
                        <?=$example['display-name']?>
                    </a>
                </li>
                
                <?php } ?>
            </ul>
        </section>
        <section class="upload">
            <h1 class="assertive">Or upload your own:</h1>
            <div class="buttons">
                <div class="select-text-button button">
                    <button id="select-text" class="primary" onclick="fileInput.click()">Select Text</button>
                    <input class="button-label" type="file" id="fileInput">
                </div>
                <!-- <input type="number" value="1" id="depthInput" placeholder="Depth" /> -->
                <div class="find-hapaxes-button button">
                    <button id="find-hapaxes" class="highlight" onclick="processFile()">Find Hapaxes</button>
                    <label class="button-label" for="find-hapaxes">Note: currently can only process plaintext files</label>
                </div>
            </div>
        </section>
    </main>

    <section class="hapax-output" id="hapax-output">
        <div class="hapax-card">
            <div class="top-bar">
                <h1 class="assertive" id="status">Waiting & Ready...</h1>
                <div class="buttons">
                    <button id="export" class="primary" onclick="exportHapaxes()" disabled>Export</button>
                    <button id="clear" class="quiet" onclick="clearHapaxes()" disabled>Clear</button>
                </div>
            </div>
            <div id="output"></div>
        </div>
    </section>
    
    
    <script>
        // Initialize the WebAssembly module
        var Module = {
            onRuntimeInitialized: function() {
                console.log('WASM loaded');
            }
        };
    </script>

    <script src="hapax.js"></script>

    <script>
        let currentHapaxes = [];

        function processFile() {

            document.getElementById('hapax-output').scrollIntoView({ behavior:'smooth' });

            clearHapaxes();
            
            document.getElementById('status').textContent = 'Processing...';

            const fileInput = document.getElementById('fileInput');
            // const depthInput = document.getElementById('depthInput');
            const output = document.getElementById('output');

            if (fileInput.files.length === 0) {
                output.textContent = 'Please select a file!';
                return;
            }


            const file = fileInput.files[0];
            // disabling depth for now, not actually needed
            // const depth = depthInput.value;
            const depth = 1;

            if (!depth) {
                output.textContent = 'Please enter a depth value!';
                return;
            }

            const reader = new FileReader();

            reader.onload = function(event) {
                const content = event.target.result;

                // Create a virtual filesystem in the browser
                FS.writeFile('input.txt', content);
                FS.open('output.txt', 'w+');

                // Run the WebAssembly module
                Module.callMain(['input.txt', 'output.txt', depth]);

                // Read the output file from the virtual filesystem
                const result = FS.readFile('output.txt', { encoding: 'utf8' });
                output.textContent = result;

                // I had to use the following janky code in order to count the number of hapaxes. I think it's much slower than if I just got the C++ to also return a count of hapaxes.
                const hapaxesArray = result.split('\n').filter(word => word.trim() !== ''); // Split into lines and filter out empty strings
                currentHapaxes = hapaxesArray; // Store as an array of hapaxes
                


                document.getElementById('status').textContent = `Found ${currentHapaxes.length.toLocaleString()} Hapaxes`;

                document.getElementById('export').disabled = false;
                document.getElementById('clear').disabled = false;


            };

            reader.readAsText(file);
        }

        function fetchHapaxes(filename) {

            document.getElementById('hapax-output').scrollIntoView({ behavior:'smooth' });

            clearHapaxes();
            
            document.getElementById('status').textContent = 'Processing...';

            setTimeout(() => {
                // this currently is slower than actually processing the files with C++... so I should maybe find a C++ / WebAssembly solution
                // particularly noticeable with larger files >200kb, e.g. joyce-hapaxes.txt
                fetch(`get_hapaxes.php?filename=${filename}`)
                    .then(response => response.json())
                    .then(data => {
                        const resultDiv = document.getElementById('output');
                        const statusDiv = document.getElementById('status');
                        if (data.error) {
                            resultDiv.innerHTML += '<p>${data.error}</p>';
                            statusDiv.textContent = 'Error: Could not retrieve hapaxes.';
                        } else {

                            const list = document.createElement('ul');
                            currentHapaxes = data;
                            data.forEach(word => {
                                const listItem = document.createElement('li');
                                listItem.textContent = word;
                                list.appendChild(listItem);
                            });

                            resultDiv.appendChild(list);

                            statusDiv.textContent = `Found ${data.length.toLocaleString()} Hapaxes`;

                            document.getElementById('export').disabled = false;
                            document.getElementById('clear').disabled = false;
                        }
                    });

            }, 500);

            
            
        }

        function clearHapaxes() {
            document.getElementById('output').innerHTML = '';
            document.getElementById('clear').disabled = true;
            document.getElementById('export').disabled = true;
            currentHapaxes = [];
            document.getElementById('status').textContent = 'Waiting & Ready...';
        }

        function exportHapaxes() {
            const blob = new Blob([currentHapaxes.join('\n')], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'hapaxes.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
