<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Dynamic Formatting Test</title>
  <style>
    #detailsEditable {
      border: 1px solid #ccc;
      padding: 8px;
      min-height: 100px;
    }
    #log {
      border: 1px solid #ccc;
      padding: 8px;
      height: 150px;
      overflow: auto;
      white-space: pre-wrap;
    }
  </style>
</head>
<body>
  <h1>Dynamic Formatting Test Page</h1>
  <div id="detailsEditable" contenteditable="true"></div>
  <input type="hidden" id="detailsInput">
  <h2>Log</h2>
  <pre id="log"></pre>
  <script>
    window.dynamicFormattingEnabled = true;
    window.dynamicFormattingDebug = true;
    (function() {
      const logEl = document.getElementById('log');
      const origLog = console.log;
      console.log = function(...args) {
        origLog.apply(console, args);
        const msg = args.map(a => {
          if (typeof a === 'object') {
            try { return JSON.stringify(a); } catch (e) { return '[Object]'; }
          }
          return String(a);
        }).join(' ');
        logEl.textContent += msg + '\n';
      };
    })();
  </script>
  <script src="dynamic-formatting.js"></script>
</body>
</html>
