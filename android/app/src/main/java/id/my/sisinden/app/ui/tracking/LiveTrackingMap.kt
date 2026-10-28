package id.my.sisinden.app.ui.tracking

import android.animation.ValueAnimator
import android.content.Context
import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.Paint
import android.graphics.Path
import android.graphics.drawable.BitmapDrawable
import android.view.animation.LinearInterpolator
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.viewinterop.AndroidView
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.lifecycle.compose.LocalLifecycleOwner
import id.my.sisinden.app.BuildConfig
import id.my.sisinden.app.data.model.TrackPointDto
import id.my.sisinden.app.data.model.TrackedPersonnelDto
import org.osmdroid.config.Configuration
import org.osmdroid.tileprovider.tilesource.TileSourceFactory
import org.osmdroid.util.BoundingBox
import org.osmdroid.util.GeoPoint
import org.osmdroid.views.MapView
import org.osmdroid.views.overlay.Marker
import org.osmdroid.views.overlay.Polyline

/** Pusat awal peta bila belum ada posisi personel (Surabaya, Koarmada 2). */
private val DEFAULT_CENTER = GeoPoint(-7.2575, 112.7521)

private const val ONLINE_COLOR = 0xFF16A34A.toInt()
private const val OFFLINE_COLOR = 0xFF94A3B8.toInt()
private const val SELECTED_COLOR = 0xFF2563EB.toInt()

/**
 * Peta live gaya aplikasi ojek daring: marker bergeser halus dari posisi lama ke
 * posisi baru selama [animateMs], panah mengikuti arah gerak, dan personel yang
 * dipilih diikuti kamera beserta jejak rutenya.
 */
@Composable
fun LiveTrackingMap(
    personnel: List<TrackedPersonnelDto>,
    selectedId: Int?,
    trail: List<TrackPointDto>,
    animateMs: Long,
    onSelect: (Int?) -> Unit,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    val holder = remember { MapHolder(context) }
    val lifecycle = LocalLifecycleOwner.current.lifecycle

    DisposableEffect(lifecycle) {
        val observer = LifecycleEventObserver { _, event ->
            when (event) {
                Lifecycle.Event.ON_RESUME -> holder.map.onResume()
                Lifecycle.Event.ON_PAUSE -> holder.map.onPause()
                else -> Unit
            }
        }
        lifecycle.addObserver(observer)
        onDispose {
            lifecycle.removeObserver(observer)
            holder.dispose()
        }
    }

    LaunchedEffect(personnel, selectedId) {
        holder.update(personnel, selectedId, animateMs, onSelect)
    }
    LaunchedEffect(trail, selectedId) {
        holder.setTrail(if (selectedId == null) emptyList() else trail)
    }

    AndroidView(factory = { holder.map }, modifier = modifier)
}

private class MapHolder(context: Context) {
    val map: MapView
    private val markers = HashMap<Int, Marker>()
    private val animators = HashMap<Int, ValueAnimator>()
    private val icons = HashMap<String, BitmapDrawable>()
    private val trailLine = Polyline()
    private var fitted = false
    private var lastSelected: Int? = null
    private val density = context.resources.displayMetrics.density
    private val res = context.resources

    init {
        // Kebijakan server tile OSM mewajibkan User-Agent aplikasi; cache disimpan di cache aplikasi.
        Configuration.getInstance().apply {
            userAgentValue = "Sinden2-Android/${BuildConfig.VERSION_NAME}"
            osmdroidBasePath = context.cacheDir.resolve("osmdroid")
            osmdroidTileCache = context.cacheDir.resolve("osmdroid/tiles")
        }
        map = MapView(context).apply {
            setTileSource(TileSourceFactory.MAPNIK)
            setMultiTouchControls(true)
            zoomController.setVisibility(org.osmdroid.views.CustomZoomButtonsController.Visibility.SHOW_AND_FADEOUT)
            controller.setZoom(12.0)
            controller.setCenter(DEFAULT_CENTER)
            minZoomLevel = 4.0
            maxZoomLevel = 19.0
        }
        trailLine.outlinePaint.apply {
            color = SELECTED_COLOR
            strokeWidth = 5f * density
            alpha = 190
            isAntiAlias = true
            strokeCap = Paint.Cap.ROUND
        }
        map.overlays.add(trailLine)
    }

    fun update(personnel: List<TrackedPersonnelDto>, selectedId: Int?, animateMs: Long, onSelect: (Int?) -> Unit) {
        val seen = HashSet<Int>()
        for (p in personnel) {
            if (p.latitude == 0.0 && p.longitude == 0.0) continue
            seen += p.userId
            val target = GeoPoint(p.latitude, p.longitude)
            val selected = p.userId == selectedId
            val moving = (p.speed ?: 0f) >= 1f && p.heading != null
            val marker = markers.getOrPut(p.userId) {
                Marker(map).apply {
                    position = target
                    setAnchor(Marker.ANCHOR_CENTER, Marker.ANCHOR_CENTER)
                    isFlat = true
                    setOnMarkerClickListener { m, _ ->
                        onSelect(p.userId)
                        m.showInfoWindow()
                        true
                    }
                    map.overlays.add(this)
                }
            }
            marker.title = p.displayName.ifBlank { "Personel #${p.userId}" }
            marker.snippet = listOfNotNull(
                if (p.isOnline) "Online" else "Offline",
                p.speed?.takeIf { it >= 1f }?.let { "${(it * 3.6f).toInt()} km/j" },
                p.battery?.let { "Baterai $it%" },
            ).joinToString(" · ")
            marker.icon = icon(if (selected) SELECTED_COLOR else if (p.isOnline) ONLINE_COLOR else OFFLINE_COLOR, moving)
            marker.rotation = if (moving) -(p.heading ?: 0f) else 0f
            animateTo(p.userId, marker, target, animateMs)
            if (selected && marker.isInfoWindowShown) marker.showInfoWindow()
        }
        // Personel yang tidak lagi dikirim server (logout/nonaktif) dihapus dari peta.
        markers.keys.filter { it !in seen }.forEach { id ->
            animators.remove(id)?.cancel()
            markers.remove(id)?.let { it.closeInfoWindow(); map.overlays.remove(it) }
        }

        val selectedMarker = selectedId?.let { markers[it] }
        if (selectedMarker != null) {
            if (lastSelected != selectedId) {
                map.controller.animateTo(selectedMarker.position, maxOf(map.zoomLevelDouble, 16.0), 600L)
                selectedMarker.showInfoWindow()
            }
        } else if (!fitted && markers.isNotEmpty()) {
            fitAll()
        }
        lastSelected = selectedId
        map.invalidate()
    }

    /** Geser marker secara halus (interpolasi linear) agar pergerakan terlihat mulus. */
    private fun animateTo(id: Int, marker: Marker, target: GeoPoint, durationMs: Long) {
        val start = marker.position
        if (start.latitude == target.latitude && start.longitude == target.longitude) return
        animators.remove(id)?.cancel()
        // Loncatan jauh (mis. baru online di kota lain) langsung dipindah tanpa animasi.
        if (start.distanceToAsDouble(target) > 5_000) {
            marker.position = target
            followIfSelected(id, target)
            return
        }
        val a = ValueAnimator.ofFloat(0f, 1f).apply {
            duration = durationMs
            interpolator = LinearInterpolator()
            addUpdateListener { va ->
                val f = va.animatedFraction.toDouble()
                val pos = GeoPoint(
                    start.latitude + (target.latitude - start.latitude) * f,
                    start.longitude + (target.longitude - start.longitude) * f,
                )
                marker.position = pos
                followIfSelected(id, pos)
                map.invalidate()
            }
        }
        animators[id] = a
        a.start()
    }

    private fun followIfSelected(id: Int, pos: GeoPoint) {
        if (id == lastSelected) {
            map.controller.setCenter(pos)
            trailLine.actualPoints.lastOrNull()?.let { last ->
                if (last.distanceToAsDouble(pos) > 3) trailLine.addPoint(pos)
            }
        }
    }

    fun setTrail(points: List<TrackPointDto>) {
        trailLine.setPoints(points.map { GeoPoint(it.latitude, it.longitude) })
        map.invalidate()
    }

    private fun fitAll() {
        val pts = markers.values.map { it.position }
        fitted = true
        if (pts.size == 1) {
            map.controller.setCenter(pts.first())
            map.controller.setZoom(15.0)
            return
        }
        map.post {
            runCatching { map.zoomToBoundingBox(BoundingBox.fromGeoPointsSafe(pts).increaseByScale(1.3f), false) }
        }
    }

    /** Ikon bulat berwarna; saat bergerak berisi panah yang diputar sesuai arah. */
    private fun icon(color: Int, arrow: Boolean): BitmapDrawable = icons.getOrPut("$color-$arrow") {
        val size = (30 * density).toInt()
        val bmp = Bitmap.createBitmap(size, size, Bitmap.Config.ARGB_8888)
        val c = Canvas(bmp)
        val r = size / 2f
        val paint = Paint(Paint.ANTI_ALIAS_FLAG)
        paint.color = 0x33000000
        c.drawCircle(r, r + density, r - density, paint)
        paint.color = 0xFFFFFFFF.toInt()
        c.drawCircle(r, r, r - 2 * density, paint)
        paint.color = color
        c.drawCircle(r, r, r - 5 * density, paint)
        paint.color = 0xFFFFFFFF.toInt()
        if (arrow) {
            val path = Path().apply {
                moveTo(r, r - 8 * density)
                lineTo(r + 6 * density, r + 7 * density)
                lineTo(r, r + 3.5f * density)
                lineTo(r - 6 * density, r + 7 * density)
                close()
            }
            c.drawPath(path, paint)
        } else {
            c.drawCircle(r, r, 4 * density, paint)
        }
        BitmapDrawable(res, bmp)
    }

    fun dispose() {
        animators.values.forEach { it.cancel() }
        animators.clear()
        map.onDetach()
    }
}
