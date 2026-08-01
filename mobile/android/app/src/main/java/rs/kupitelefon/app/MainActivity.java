package rs.kupitelefon.app;

import android.annotation.SuppressLint;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Color;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.View;
import android.view.ViewGroup;
import android.webkit.CookieManager;
import android.webkit.JavascriptInterface;
import android.webkit.WebView;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsControllerCompat;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;
import com.getcapacitor.BridgeActivity;
import org.json.JSONObject;

public class MainActivity extends BridgeActivity {
    private static final String SITE_ORIGIN = "https://kupitelefon.rs";
    private static final String PREFS = "kt_native";
    private static final String KEY_PENDING = "pending_link";

    private final Handler mainHandler = new Handler(Looper.getMainLooper());
    private SwipeRefreshLayout swipeRefresh;
    private boolean swipeAttached = false;

    private static final int NAV_BAR_GRAY = Color.parseColor("#C8C8C8");

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        applySystemBars();
        CookieManager.getInstance().setAcceptCookie(true);
        setupWebViewHelpers();
        captureDeepLink(getIntent());
        scheduleDeepLinkNavigation();
    }

    @Override
    public void onResume() {
        super.onResume();
        applySystemBars();
    }

    @Override
    public void onWindowFocusChanged(boolean hasFocus) {
        super.onWindowFocusChanged(hasFocus);
        if (hasFocus) {
            applySystemBars();
        }
    }

    /** Sistemska navigacija telefona (nazad/home) na sivoj traci — bez immersive/fullscreen. */
    private void applySystemBars() {
        WindowCompat.setDecorFitsSystemWindows(getWindow(), true);
        getWindow().setStatusBarColor(Color.WHITE);
        getWindow().setNavigationBarColor(NAV_BAR_GRAY);
        View decor = getWindow().getDecorView();
        decor.setSystemUiVisibility(View.SYSTEM_UI_FLAG_VISIBLE);
        WindowInsetsControllerCompat insets = WindowCompat.getInsetsController(getWindow(), decor);
        if (insets != null) {
            insets.setAppearanceLightStatusBars(true);
            insets.setAppearanceLightNavigationBars(true);
            insets.setSystemBarsBehavior(
                WindowInsetsControllerCompat.BEHAVIOR_DEFAULT
            );
            insets.show(androidx.core.view.WindowInsetsCompat.Type.systemBars());
            insets.show(androidx.core.view.WindowInsetsCompat.Type.navigationBars());
        }
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
        attachPullToRefresh(webView);
    }

    private void attachPullToRefresh(WebView webView) {
        if (swipeAttached || webView.getParent() == null) {
            return;
        }
        ViewGroup parent = (ViewGroup) webView.getParent();
        int index = parent.indexOfChild(webView);
        ViewGroup.LayoutParams params = webView.getLayoutParams();
        parent.removeView(webView);

        swipeRefresh = new SwipeRefreshLayout(this);
        swipeRefresh.setColorSchemeColors(Color.parseColor("#3D9A50"), Color.parseColor("#F5C518"));
        swipeRefresh.addView(
            webView,
            new ViewGroup.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT)
        );
        parent.addView(swipeRefresh, index, params);

        swipeRefresh.setOnRefreshListener(() -> {
            String js =
                "(function(){"
                    + "try{"
                    + "if(window.location.pathname.indexOf('/poruke')===0||window.location.pathname.indexOf('/nalog')===0){"
                    + "window.location.reload();"
                    + "}else{"
                    + "window.location.href='https://kupitelefon.rs/index.php';"
                    + "}"
                    + "}catch(e){window.location.reload();}"
                    + "})();";
            webView.evaluateJavascript(js, null);
            mainHandler.postDelayed(() -> {
                if (swipeRefresh != null) {
                    swipeRefresh.setRefreshing(false);
                }
            }, 1200);
        });

        webView.setOnScrollChangeListener((v, scrollX, scrollY, oldScrollX, oldScrollY) -> {
            if (swipeRefresh != null) {
                swipeRefresh.setEnabled(scrollY <= 2);
            }
        });
        swipeAttached = true;
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
        if (link == null || "FCM_PLUGIN_ACTIVITY".equals(link)) {
            link = bundleString(extras, "click_action");
        }
        if ("FCM_PLUGIN_ACTIVITY".equals(link)) {
            link = null;
        }
        boolean fromPush = extras != null && (
            extras.containsKey("google.message_id")
                || extras.containsKey("google.delivered_priority")
                || extras.containsKey("gcm.n.e")
                || extras.containsKey("google.product_id")
                || (extras.containsKey("from") && String.valueOf(extras.get("from")).contains("google"))
        );
        // Tap na notifikaciju bez linka → Poruke
        if ((link == null || link.isEmpty()) && fromPush) {
            link = SITE_ORIGIN + "/poruke.php";
        }
        if (link == null || link.isEmpty()) {
            return;
        }
        if (link.startsWith("/")) {
            link = SITE_ORIGIN + link;
        }
        if (!link.startsWith("https://kupitelefon.rs") && !link.startsWith("http://kupitelefon.rs")) {
            if (fromPush) {
                link = SITE_ORIGIN + "/poruke.php";
            } else {
                return;
            }
        }
        prefs().edit().putString(KEY_PENDING, link).apply();
    }

    private void scheduleDeepLinkNavigation() {
        for (int i = 0; i < 10; i++) {
            final int attempt = i;
            mainHandler.postDelayed(this::applyPendingDeepLink, 400L + attempt * 600L);
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
        // Jednom otvori, pa očisti — inače 10 retry-ja reloaduje u krug
        prefs().edit().remove(KEY_PENDING).apply();
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
