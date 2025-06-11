import { BrowserRouter, Routes, Route } from "react-router-dom";
import { Home, Layout, GoalList } from "./pages";
import CreateGoalModal from "./components/organism/ CreateGoalModal";

function Router() {
    return (
        <BrowserRouter>
            <Routes>
                <Route path="/" element={<Layout />}>
                    <Route index element={<Home />} />
                    {/* <Route path="create-goal" element={<CreateAGoal />} /> */}
                    <Route path="create-goal" element={<CreateGoalModal />} />
                    <Route path="goal-list" element={<GoalList />} />
                    {/* Add more routes as needed */}
                </Route>
            </Routes>
        </BrowserRouter>
    );
}

export default Router;
