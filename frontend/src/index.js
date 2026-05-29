import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';

// Suppress a known benign browser error from ResizeObserver in some environments
// See: https://developer.mozilla.org/en-US/docs/Web/API/ResizeObserver#exceptions
window.addEventListener('error', (event) => {
  try {
    const msg = event && event.message ? event.message : '';
    if (msg && msg.indexOf('ResizeObserver loop completed with undelivered notifications') !== -1) {
      // Stop the error from bubbling to the console and interrupting runtime
      event.stopImmediatePropagation();
      event.preventDefault && event.preventDefault();
      return true;
    }
  } catch (e) {
    // ignore
  }
});

const root = ReactDOM.createRoot(document.getElementById('root'));
root.render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
);
