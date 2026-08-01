package rs.kupitelefon.app;

import android.annotation.SuppressLint;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Color;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.View;
import android.webkit.CookieManager;
import android.webkit.JavascriptInterface;
import android.webkit.WebView;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsControllerCompat;
import com.getcapacitor.BridgeActivity;
import org.json.JSONObject;

public class MainActivity extends BridgeActivity {
    private static final String SITE_ORIGIN = "https://kupitelefon.rs";
    private static final String PREFS = "kt_native";
    private static final String KEY_PENDING = "pending_link";

    private final Handler mainHandler = new Handler(Looper.getMainLooper());

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        WindowCompat.setDecorFitsSystemWindows(getWindow(), true);
        getWindow().setStatusBarColor(Color.WHITE);
        getWindow().setNavigationBarColor(Color.parseColor("#F2F2F2"));
        WindowInsetsControllerCompat insets = WindowCompat.getInsetsController(getWindow(), getWindow().getDecorView());
        if (insets != null) {
            insets.setAppearanceLightStatusBars(true);
            insets.setAppearanceLightNavigationBars(true);
        }

        CookieManager cookies = CookieManager.getInstance();
        cookies.setAcceptCookie(true);

        setupWebViewHelpers();
        captureDeepLink(getIntent());
        scheduleDeepLinkNavigation();
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        captureDeepLink(intent);
        scheduleDeepLinkNavigation();
    }

    @SuppressLint("SetJavaScriptEnabled")
    private void setupWebViewHelpers() {
        if (getBridge() == null || getBridge().getWebView() == null) {
            mainHandler.postDelayed(this::setupWebViewHelpers, 250);
            return;
        }
        WebView webView = getBridge().getWebView();
        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, true);
        webView.setOverScrollMode(View.OVER_SCROLL_NEVER);
        webView.setBackgroundColor(Color.parseColor("#F2F2F2"));
        webView.addJavascriptInterface(new KtBridge(), "KtNative");
    }

    private SharedPreferences prefs() {
        return getSharedPreferences(PREFS, MODE_PRIVATE);
    }

    private String bundleString(Bundle extras, String key) {
        if (extras == null || !extras.containsKey(key)) {
            return null;
        }
        Object value = extras.get(key);
        if (value == null) {
            return null;
        }
        String out = String.valueOf(value).trim();
        return out.isEmpty() ? null : out;
    }

    private void captureDeepLink(Intent intent) {
        if (intent == null) {
            return;
        }
        Bundle extras = intent.getExtras();
        String link = bundleString(extras, "link");
        if (link == null) {
            link = bundleString(extras, "click_action");
        }
        boolean fromPush = extras != null && (
            extras.containsKey("google.message_id")
                || extras.containsKey("google.delivered_priority")
                || extras.containsKey("from")
        );
        if ((link == null || "FCM_PLUGIN_ACTIVITY".equals(link)) && fromPush) {
            link = SITE_ORIGIN + "/poruke.php";
        }
        if (link == null || link.isEmpty() || "FCM_PLUGIN_ACTIVITY".equals(link)) {
            return;
        }
        if (link.startsWith("/")) {
            link = SITE_ORIGIN + link;
        }
        if (!link.startsWith("https://kupitelefon.rs") && !link.startsWith("http://kupitelefon.rs")) {
            // Ako je samo path-like bez sheme iz data
            if (link.contains("poruke")) {
                link = SITE_ORIGIN + "/poruke.php";
            } else {
                return;
            }
        }
        prefs().edit().putString(KEY_PENDING, link).apply();
    }

    private void scheduleDeepLinkNavigation() {
        // Više pokušaja jer Capacitor prvo učitava server.url (homepage)
        for (int i = 0; i < 8; i++) {
            final int attempt = i;
            mainHandler.postDelayed(this::applyPendingDeepLink, 500L + attempt * 700L);
        }
    }

    private void applyPendingDeepLink() {
        String link = prefs().getString(KEY_PENDING, null);
        if (link == null || link.isEmpty()) {
            return;
        }
        if (getBridge() == null || getBridge().getWebView() == null) {
            return;
        }
        WebView webView = getBridge().getWebView();
        webView.loadUrl(link);
        try {
            String js = "(function(){try{window.location.replace(" + JSONObject.quote(link) + ");}catch(e){}})();";
            webView.evaluateJavascript(js, null);
        } catch (Exception ignored) {
        }
    }

    public class KtBridge {
        @JavascriptInterface
        public String getPendingLink() {
            return prefs().getString(KEY_PENDING, "");
        }

        @JavascriptInterface
        public void clearPendingLink() {
            prefs().edit().remove(KEY_PENDING).apply();
        }
    }

    @Override
    public void onPause() {
        CookieManager.getInstance().flush();
        super.onPause();
    }

    @Override
    public void onStop() {
        CookieManager.getInstance().flush();
        super.onStop();
    }
}
