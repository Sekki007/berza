package rs.kupitelefon.app;

import android.annotation.SuppressLint;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.ActivityInfo;
import android.content.res.Configuration;
import android.graphics.Color;
import android.net.Uri;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.MotionEvent;
import android.view.View;
import android.view.ViewGroup;
import android.webkit.CookieManager;
import android.webkit.JavascriptInterface;
import android.webkit.WebView;
import androidx.activity.OnBackPressedCallback;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.core.view.WindowInsetsControllerCompat;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;
import com.getcapacitor.BridgeActivity;
import org.json.JSONObject;

public class MainActivity extends BridgeActivity {
    private static final String SITE_ORIGIN = "https://kupitelefon.rs";
    private static final String PREFS = "kt_native";
    private static final String KEY_PENDING = "pending_link";
    private static final int NAV_BAR_GRAY = Color.parseColor("#C8C8C8");

    private final Handler mainHandler = new Handler(Looper.getMainLooper());
    private SwipeRefreshLayout swipeRefresh;
    private boolean swipeAttached = false;
    private boolean edgeBackAttached = false;
    private boolean edgeToEdgeSetup = false;

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        applyOrientationPolicy();
        setupEdgeToEdge();
        applySystemBars();
        CookieManager.getInstance().setAcceptCookie(true);
        registerBackHandler();
        setupWebViewHelpers();
        captureDeepLink(getIntent());
        scheduleDeepLinkNavigation();
    }

    /** Telefon = samo portrait; tablet (sw >= 600dp) = slobodna rotacija. */
    private void applyOrientationPolicy() {
        int smallest = getResources().getConfiguration().smallestScreenWidthDp;
        if (smallest >= 600) {
            setRequestedOrientation(ActivityInfo.SCREEN_ORIENTATION_FULL_USER);
        } else {
            setRequestedOrientation(ActivityInfo.SCREEN_ORIENTATION_PORTRAIT);
        }
    }

    @Override
    public void onConfigurationChanged(Configuration newConfig) {
        super.onConfigurationChanged(newConfig);
        applyOrientationPolicy();
        applySystemBars();
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

    /**
     * Edge-to-edge (Android 16) + tastatura: bottom padding mora da uključi IME,
     * inače WebView ne smanjuje viewport i chat polje ostaje ispod tastature.
     */
    private void setupEdgeToEdge() {
        if (edgeToEdgeSetup) {
            return;
        }
        edgeToEdgeSetup = true;
        WindowCompat.setDecorFitsSystemWindows(getWindow(), false);
        View content = findViewById(android.R.id.content);
        if (content != null) {
            ViewCompat.setOnApplyWindowInsetsListener(content, (v, windowInsets) -> {
                Insets bars = windowInsets.getInsets(WindowInsetsCompat.Type.systemBars());
                Insets ime = windowInsets.getInsets(WindowInsetsCompat.Type.ime());
                int bottom = Math.max(bars.bottom, ime.bottom);
                v.setPadding(bars.left, bars.top, bars.right, bottom);
                return WindowInsetsCompat.CONSUMED;
            });
            ViewCompat.requestApplyInsets(content);
        }
    }

    /** Sistemska navigacija telefona (nazad/home) na sivoj traci — bez immersive/fullscreen. */
    private void applySystemBars() {
        getWindow().setStatusBarColor(Color.WHITE);
        getWindow().setNavigationBarColor(NAV_BAR_GRAY);
        View decor = getWindow().getDecorView();
        decor.setSystemUiVisibility(View.SYSTEM_UI_FLAG_VISIBLE);
        WindowInsetsControllerCompat insets = WindowCompat.getInsetsController(getWindow(), decor);
        if (insets != null) {
            insets.setAppearanceLightStatusBars(true);
            insets.setAppearanceLightNavigationBars(true);
            insets.setSystemBarsBehavior(WindowInsetsControllerCompat.BEHAVIOR_DEFAULT);
            insets.show(androidx.core.view.WindowInsetsCompat.Type.systemBars());
            insets.show(androidx.core.view.WindowInsetsCompat.Type.navigationBars());
        }
    }

    private void registerBackHandler() {
        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                if (goBackInWebView()) {
                    return;
                }
                // Na početnoj — minimiziraj app umesto da je ubije
                moveTaskToBack(true);
            }
        });
    }

    private boolean goBackInWebView() {
        if (getBridge() == null || getBridge().getWebView() == null) {
            return false;
        }
        WebView webView = getBridge().getWebView();
        if (webView.canGoBack()) {
            webView.goBack();
            return true;
        }
        return false;
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
        attachEdgeBackGesture(webView);
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
            // Osveži trenutnu stranicu (ne vraćaj uvek na home)
            webView.reload();
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

    /** Povuci od leve ivice → nazad u istoriji (kao browser). */
    @SuppressLint("ClickableViewAccessibility")
    private void attachEdgeBackGesture(WebView webView) {
        if (edgeBackAttached) {
            return;
        }
        final float density = getResources().getDisplayMetrics().density;
        final float edgePx = 36f * density;
        final float minSwipe = 72f * density;
        final float maxVertical = 80f * density;

        webView.setOnTouchListener(new View.OnTouchListener() {
            float startX;
            float startY;
            boolean tracking;

            @Override
            public boolean onTouch(View v, MotionEvent event) {
                switch (event.getActionMasked()) {
                    case MotionEvent.ACTION_DOWN:
                        tracking = event.getX() <= edgePx;
                        startX = event.getX();
                        startY = event.getY();
                        return false;
                    case MotionEvent.ACTION_UP:
                    case MotionEvent.ACTION_CANCEL:
                        if (tracking) {
                            float dx = event.getX() - startX;
                            float dy = Math.abs(event.getY() - startY);
                            tracking = false;
                            if (dx >= minSwipe && dy <= maxVertical && goBackInWebView()) {
                                return true;
                            }
                        }
                        tracking = false;
                        return false;
                    default:
                        return false;
                }
            }
        });
        edgeBackAttached = true;
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

    private boolean isOurHost(String host) {
        return "kupitelefon.rs".equalsIgnoreCase(host) || "www.kupitelefon.rs".equalsIgnoreCase(host);
    }

    private String normalizeSiteUrl(String link) {
        if (link == null || link.isEmpty()) {
            return null;
        }
        if (link.startsWith("/")) {
            link = SITE_ORIGIN + link;
        }
        if (link.startsWith("http://kupitelefon.rs")) {
            link = "https://" + link.substring("http://".length());
        }
        if (link.startsWith("https://www.kupitelefon.rs")) {
            link = SITE_ORIGIN + link.substring("https://www.kupitelefon.rs".length());
        }
        if (link.startsWith("http://www.kupitelefon.rs")) {
            link = SITE_ORIGIN + link.substring("http://www.kupitelefon.rs".length());
        }
        if (!link.startsWith(SITE_ORIGIN)) {
            return null;
        }
        return link;
    }

    private void captureDeepLink(Intent intent) {
        if (intent == null) {
            return;
        }

        // App Link / otvoren URL (npr. /oglas/123-...)
        Uri data = intent.getData();
        if (data != null && isOurHost(data.getHost())) {
            String link = normalizeSiteUrl(data.toString());
            if (link != null) {
                prefs().edit().putString(KEY_PENDING, link).apply();
                return;
            }
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
        if ((link == null || link.isEmpty()) && fromPush) {
            link = SITE_ORIGIN + "/poruke.php";
        }
        if (link == null || link.isEmpty()) {
            return;
        }
        String normalized = normalizeSiteUrl(link);
        if (normalized == null) {
            if (fromPush) {
                normalized = SITE_ORIGIN + "/poruke.php";
            } else {
                return;
            }
        }
        prefs().edit().putString(KEY_PENDING, normalized).apply();
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

        @JavascriptInterface
        public void share(String title, String url) {
            final String shareTitle = title == null ? "" : title.trim();
            final String shareUrl = url == null ? "" : url.trim();
            mainHandler.post(() -> {
                try {
                    Intent send = new Intent(Intent.ACTION_SEND);
                    send.setType("text/plain");
                    if (!shareTitle.isEmpty()) {
                        send.putExtra(Intent.EXTRA_SUBJECT, shareTitle);
                    }
                    String text = shareTitle.isEmpty() ? shareUrl : (shareTitle + "\n" + shareUrl);
                    send.putExtra(Intent.EXTRA_TEXT, text);
                    startActivity(Intent.createChooser(send, "Podeli oglas"));
                } catch (Exception ignored) {
                }
            });
        }

        @JavascriptInterface
        public void goBack() {
            mainHandler.post(() -> {
                if (!goBackInWebView()) {
                    moveTaskToBack(true);
                }
            });
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
