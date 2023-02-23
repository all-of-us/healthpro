$(document).ready(function () {
    const youTubeVideos = $("#you-tube-videos");
    const type = youTubeVideos.data("type");
    const youTubePath = "https://www.youtube.com/embed";
    const helpVideosPath = youTubeVideos.data("help-videos-path");

    const loadVideo = function (src) {
        $("#video-title").html(src.html());
        if (type === "file") {
            $("#video-file source").attr("src", helpVideosPath + "/" + src.data("file-src"));
            $("#video-file")[0].load();
        } else {
            $("iframe#video-embed").attr("src", youTubePath + "/" + src.data("embed-src") + "?modestbranding=1&rel=0");
        }
        $("a.load-video").removeClass("active");
        src.addClass("active");
    };

    $("a.load-video").on("click", function () {
        loadVideo($(this));
        $("html").scrollTop($("#video-panel").offset().top);
        return false;
    });
    loadVideo($("a.load-video:first"));
});
