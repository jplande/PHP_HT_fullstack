import { BrowserRouter, Routes, Route } from "react-router-dom";
import { Home, CreateAGoal, Layout } from "./pages";

function Router() {
    return (
        <BrowserRouter>
            <Routes>
                <Route path="/" element={<Layout />}>
                    <Route index element={<Home />} />
                    <Route path="create-goal" element={<CreateAGoal />} />
                </Route>
            </Routes>
        </BrowserRouter>
    );
}

export default Router;
